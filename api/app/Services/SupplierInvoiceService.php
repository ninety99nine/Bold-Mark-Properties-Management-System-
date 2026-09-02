<?php

namespace App\Services;

use App\Enums\FinancialCategory;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Enums\JournalSource;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierInvoiceType;
use App\Models\Community;
use App\Models\Ledger;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * WeConnectU "Supplier Invoices" (GRVs). Captures a supplier invoice against a
 * community with one or more account (ledger) lines entered VAT-inclusive, the
 * WeConnectU way. A GRV can be saved as a Draft or Created; a Created GRV posts
 * a CREDIT to the supplier ledger (see {@see SupplierLedgerService}) and is aged
 * by its invoice date in the Supplier Age Analysis.
 */
class SupplierInvoiceService extends BaseService
{
    /**
     * @param SupplierLedgerService $ledger
     */
    public function __construct(private SupplierLedgerService $ledger)
    {
        parent::__construct();
    }

    /**
     * Post (or re-post) the balanced GL batch for a supplier invoice (GRV) — the
     * accrual of money owed to a supplier: Dr each item expense ledger (net, or
     * Suspense when unassigned), Dr VAT Control (Σ input VAT) and Cr Accounts
     * Payable [supplier] for the full total. The net supplier effect is a CREDIT
     * to Accounts Payable (increases what the community owes).
     *
     * Only "created" invoices post; a draft posts nothing (its batches are
     * reversed). Idempotent; skipped when the invoice has no supplier or the
     * organisation has no chart of accounts yet.
     *
     * @param SupplierInvoice $invoice
     * @return void
     */
    public function postSupplierInvoiceLedger(SupplierInvoice $invoice): void
    {
        $gl = app(GeneralLedgerPostingService::class);

        // Drafts never post — reverse any existing batch and stop.
        if ($invoice->status !== SupplierInvoiceStatus::CREATED) {
            $gl->deleteBatchesFor($invoice);
            return;
        }

        if (! $invoice->supplier_id || ! $invoice->organization_id) {
            return;
        }

        $community = $invoice->community()->first();

        if (! $community) {
            return;
        }

        $orgId = $invoice->organization_id;

        // Bail (do not throw) when the chart of accounts has not been set up.
        if (! Ledger::controlAccount($orgId, FinancialCategory::ACCOUNTS_PAYABLE)) {
            return;
        }

        $ap       = $gl->controlAccount($orgId, FinancialCategory::ACCOUNTS_PAYABLE);
        $suspense = Ledger::suspense($orgId);
        $date     = $invoice->invoice_date ?? $invoice->created_at ?? Carbon::now();

        $invoice->loadMissing('items');

        $lines    = [];
        $taxTotal = 0.0;

        foreach ($invoice->items as $item) {
            $gross = (float) $item->line_total;
            $tax   = (float) $item->tax_amount;
            $net   = round($gross - $tax, 2);
            $taxTotal += $tax;

            // Dr expense ledger (net) — the cost the invoice records.
            $lines[] = [
                'line_type'   => JournalLineType::GENERAL,
                'entry_type'  => JournalEntryType::DEBIT,
                'amount'      => $net,
                'ledger_id'   => $item->ledger_id ?: $suspense?->id,
                'description' => $item->description ?: ($item->account_name ?: $invoice->grv_number),
            ];
        }

        if (round($taxTotal, 2) > 0) {
            $vat = $gl->controlAccount($orgId, FinancialCategory::VAT_CONTROL);
            $lines[] = [
                'line_type'   => JournalLineType::GENERAL,
                'entry_type'  => JournalEntryType::DEBIT,
                'amount'      => round($taxTotal, 2),
                'ledger_id'   => $vat->id,
                'description' => 'VAT on ' . $invoice->grv_number,
            ];
        }

        // Cr Accounts Payable [supplier] for the full invoice total.
        $lines[] = [
            'line_type'   => JournalLineType::SUPPLIER,
            'entry_type'  => JournalEntryType::CREDIT,
            'amount'      => (float) $invoice->total,
            'supplier_id' => $invoice->supplier_id,
            'ledger_id'   => $ap->id,
            'description' => $invoice->grv_number,
        ];

        $gl->repostFor(
            $community,
            Carbon::parse($date),
            JournalSource::SUPPLIER_INVOICE,
            $invoice,
            'Accrual',
            $lines,
        );
    }

    /**
     * Return a paginated list of supplier invoices for a community, filtered by
     * the WeConnectU toolbar: Drafts/Created tab (status), month, supplier, type
     * and search.
     *
     * @param Community $community
     * @param array $data
     * @return \Illuminate\Http\Resources\Json\ResourceCollection
     */
    public function showSupplierInvoices(Community $community, array $data)
    {
        $user  = Auth::user();
        $query = SupplierInvoice::where('organization_id', $user->organization_id)
            ->where('community_id', $community->id)
            ->with(['supplier:id,supplier_code,name', 'items.ledger:id,code,name']);

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['supplier_id'])) {
            $query->where('supplier_id', $data['supplier_id']);
        }

        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        // Month filter (YYYY-MM) against the invoice date.
        if (! empty($data['month'])) {
            $month = Carbon::parse($data['month'] . '-01');
            $query->whereBetween('invoice_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ]);
        }

        $query->orderByDesc('invoice_date')->orderByDesc('grv_number');

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Return a single supplier invoice with its supplier + line items.
     *
     * @param SupplierInvoice $supplierInvoice
     * @return \App\Http\Resources\SupplierInvoiceResource
     */
    public function showSupplierInvoice(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load(['supplier', 'items.ledger']);

        return $this->showResource($supplierInvoice);
    }

    /**
     * Create (or draft) a supplier invoice with its account lines.
     *
     * Line amounts are entered VAT-inclusive (WeConnectU "Incl."); the header
     * caches the rolled-up subtotal / VAT / total. A "created" GRV re-posts the
     * supplier balance.
     *
     * @param Community $community
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createSupplierInvoice(Community $community, array $data): array
    {
        $user = Auth::user();

        $supplier = Supplier::where('id', $data['supplier_id'])
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $status = $data['status'] ?? SupplierInvoiceStatus::CREATED->value;

        // Roll up the VAT-inclusive line items.
        [$items, $subtotal, $vatTotal, $discountTotal, $grandTotal] = $this->rollUpItems($data['items'] ?? []);

        $invoiceDate = Carbon::parse($data['invoice_date']);

        // Persist uploaded source documents.
        $attachments = [];
        if (request()->hasFile('attachments')) {
            foreach ((array) request()->file('attachments') as $file) {
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->store("supplier-invoices/{$user->organization_id}", 'public'),
                ];
            }
        }

        $invoice = DB::transaction(function () use (
            $user, $community, $supplier, $data, $status, $invoiceDate,
            $subtotal, $vatTotal, $discountTotal, $grandTotal, $attachments, $items
        ) {
            $invoice = SupplierInvoice::create([
                'grv_number'             => $this->generateGrvNumber($user->organization_id),
                'status'                 => $status,
                'type'                   => $data['type'] ?? SupplierInvoiceType::ADHOC->value,
                'source_document_number' => $data['source_document_number'] ?? null,
                'supplier_reference'     => $data['supplier_reference'] ?? null,
                'our_reference'          => $data['our_reference'] ?? null,
                'description'            => $data['description'] ?? null,
                'attachments'            => $attachments ?: null,
                'invoice_date'           => $invoiceDate->toDateString(),
                'due_date'               => ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null,
                'financial_year'         => (int) $invoiceDate->year,
                'subtotal'               => $subtotal,
                'discount'               => $discountTotal,
                'vat_amount'             => $vatTotal,
                'total'                  => $grandTotal,
                'supplier_id'            => $supplier->id,
                'community_id'           => $community->id,
                'organization_id'        => $user->organization_id,
                'created_by_user_id'     => $user->id,
            ]);

            $invoice->items()->createMany($items);

            return $invoice;
        });

        if ($status === SupplierInvoiceStatus::CREATED->value) {
            $this->postSupplierInvoiceLedger($invoice);
            $this->ledger->recalculate($supplier);
        }

        $invoice->load(['supplier', 'items.ledger']);

        return $this->showCreatedResource($invoice);
    }

    /**
     * Update a supplier invoice's header/lines and re-post its GL batch. A change
     * of status (draft ↔ created) or of the amounts re-posts (or reverses) the
     * Accounts-Payable accrual and re-derives the supplier balance.
     *
     * @param SupplierInvoice $supplierInvoice
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function updateSupplierInvoice(SupplierInvoice $supplierInvoice, array $data): array
    {
        $user = Auth::user();

        $supplier = $supplierInvoice->supplier;

        if (! empty($data['supplier_id'])) {
            $supplier = Supplier::where('id', $data['supplier_id'])
                ->where('organization_id', $user->organization_id)
                ->firstOrFail();
        }

        DB::transaction(function () use ($supplierInvoice, $supplier, $data) {
            $attributes = [
                'supplier_id' => $supplier->id,
            ];

            foreach (['type', 'source_document_number', 'supplier_reference', 'our_reference', 'description'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $attributes['status'] = $data['status'];
            }

            if (! empty($data['invoice_date'])) {
                $invoiceDate = Carbon::parse($data['invoice_date']);
                $attributes['invoice_date']   = $invoiceDate->toDateString();
                $attributes['financial_year'] = (int) $invoiceDate->year;
            }

            if (array_key_exists('due_date', $data)) {
                $attributes['due_date'] = ! empty($data['due_date'])
                    ? Carbon::parse($data['due_date'])->toDateString()
                    : null;
            }

            if (array_key_exists('items', $data)) {
                [$items, $subtotal, $vatTotal, $discountTotal, $grandTotal] = $this->rollUpItems($data['items']);

                $attributes['subtotal']   = $subtotal;
                $attributes['discount']   = $discountTotal;
                $attributes['vat_amount'] = $vatTotal;
                $attributes['total']      = $grandTotal;

                $supplierInvoice->items()->delete();
                $supplierInvoice->items()->createMany($items);
            }

            $supplierInvoice->update($attributes);
        });

        $supplierInvoice->refresh();

        // Re-post (or reverse) the GL batch for both the (possibly new) supplier
        // and the original supplier, then re-derive both balances.
        $this->postSupplierInvoiceLedger($supplierInvoice);
        $this->ledger->recalculate($supplierInvoice->supplier);

        if ($supplier->id !== $supplierInvoice->supplier_id) {
            $this->ledger->recalculate($supplier);
        }

        $supplierInvoice->load(['supplier', 'items.ledger']);

        return $this->showCreatedResource($supplierInvoice);
    }

    /**
     * Generate and stream the WeConnectU "SUPPLIER TAX INVOICE" PDF for a GRV.
     *
     * @param SupplierInvoice $supplierInvoice
     * @return Response
     */
    public function downloadPdf(SupplierInvoice $supplierInvoice): Response
    {
        $supplierInvoice->load(['supplier', 'items.ledger', 'community']);

        $community    = $supplierInvoice->community;
        $organization = $community?->organization;

        $pdf = Pdf::loadView('pdfs.supplier-invoice', [
            'invoice'         => $supplierInvoice,
            'supplier'        => $supplierInvoice->supplier,
            'community'       => $community,
            'organization'    => $organization,
            'companyLogoPath' => $organization?->logoFilePath(),
        ])->setPaper('a4');

        return $pdf->download("{$supplierInvoice->grv_number}.pdf");
    }

    /**
     * Delete a supplier invoice (and re-post the supplier balance).
     *
     * @param SupplierInvoice $supplierInvoice
     * @return array
     */
    public function deleteSupplierInvoice(SupplierInvoice $supplierInvoice): array
    {
        $supplier = $supplierInvoice->supplier;

        // The SupplierInvoice `deleted` observer reverses the GL batch; recompute
        // the supplier balance from the (now reduced) supplier subledger.
        $deleted = $supplierInvoice->delete();

        if ($deleted && $supplier) {
            $this->ledger->recalculate($supplier);
        }

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Supplier invoice deleted' : 'Supplier invoice delete unsuccessful',
        ];
    }

    /**
     * Roll up the VAT-inclusive line items into persisted rows + header totals.
     *
     * @param array $rawItems
     * @return array{0:array,1:float,2:float,3:float,4:float}
     * @throws Exception
     */
    private function rollUpItems(array $rawItems): array
    {
        if (empty($rawItems)) {
            throw new Exception('A supplier invoice must have at least one line.');
        }

        $items    = [];
        $subtotal = 0.0;
        $vatTotal = 0.0;
        $discount = 0.0;
        $total    = 0.0;

        foreach (array_values($rawItems) as $index => $row) {
            $quantity  = (float) ($row['quantity'] ?? 1);
            $unitPrice = (float) ($row['unit_price'] ?? $row['amount'] ?? 0); // VAT-inclusive
            $lineDisc  = (float) ($row['discount'] ?? 0);
            $taxRate   = (float) ($row['tax_rate'] ?? 0);

            $lineTotal = round($quantity * $unitPrice - $lineDisc, 2);        // incl VAT
            $taxAmount = $taxRate > 0
                ? round($lineTotal - ($lineTotal / (1 + $taxRate / 100)), 2)
                : 0.0;
            $net       = round($lineTotal - $taxAmount, 2);

            $subtotal += $net;
            $vatTotal += $taxAmount;
            $discount += $lineDisc;
            $total    += $lineTotal;

            $items[] = [
                'account_name' => $row['account_name'] ?? null,
                'description'  => $row['description'] ?? null,
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'discount'     => $lineDisc,
                'tax_rate'     => $taxRate,
                'tax_amount'   => $taxAmount,
                'line_total'   => $lineTotal,
                'sort_order'   => $index,
                'ledger_id'    => $row['ledger_id'] ?? null,
            ];
        }

        return [$items, round($subtotal, 2), round($vatTotal, 2), round($discount, 2), round($total, 2)];
    }

    /**
     * Generate a sequential GRV number for the organization (e.g. "GRV00005").
     *
     * @param string $organizationId
     * @return string
     */
    private function generateGrvNumber(string $organizationId): string
    {
        $prefix = 'GRV';

        $max = SupplierInvoice::where('organization_id', $organizationId)
            ->where('grv_number', 'like', $prefix . '%')
            ->max('grv_number');

        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
