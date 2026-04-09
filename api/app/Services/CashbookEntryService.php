<?php

namespace App\Services;

use Exception;
use App\Models\CashbookEntry;
use App\Models\Invoice;
use App\Models\Unit;
use App\Enums\CashbookEntryType;
use App\Enums\InvoiceStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\CashbookEntryResource;
use App\Http\Resources\CashbookEntryResources;

class CashbookEntryService extends BaseService
{
    public function __construct(private readonly UnitBalanceService $unitBalance)
    {
        parent::__construct();
    }

    /**
     * Return a paginated, filtered list of cashbook entries for the authenticated tenant.
     *
     * @param array $data
     * @return CashbookEntryResources
     */
    public function showCashbookEntries(array $data): CashbookEntryResources
    {
        $user  = Auth::user();
        $query = CashbookEntry::where('tenant_id', $user->tenant_id)
            ->with(['unit', 'invoice']);

        if (!empty($data['estate_id'])) {
            $query->where('estate_id', $data['estate_id']);
        }

        if (!empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }

        // Allocation status filter: derived from invoice_id presence
        if (!empty($data['allocation_status'])) {
            if ($data['allocation_status'] === 'allocated') {
                $query->whereNotNull('invoice_id');
            } elseif ($data['allocation_status'] === 'unallocated') {
                $query->whereNull('invoice_id');
            }
        }

        if (!empty($data['charge_type_id'])) {
            $query->where('charge_type_id', $data['charge_type_id']);
        }

        // Date range on the transaction date column
        if (!empty($data['date_range'])) {
            $query = $this->applyDateRange(
                $query,
                $data['date_range'],
                $data['date_range_start'] ?? null,
                $data['date_range_end'] ?? null,
                'date'
            );
        }

        if (!request()->has('_sort')) {
            $query = $query->orderBy('date', 'desc');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Export cashbook entries as CSV, Excel, or PDF — same filters as showCashbookEntries().
     *
     * Extra parameters in $data:
     *   _format  — 'csv' | 'xlsx' | 'pdf'  (required)
     *   _limit   — integer record cap, or 'current' (= 15)
     *
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportCashbookEntries(array $data): \Symfony\Component\HttpFoundation\Response
    {
        $user  = Auth::user();
        $query = CashbookEntry::where('tenant_id', $user->tenant_id)
            ->with(['unit', 'invoice']);

        if (!empty($data['estate_id'])) {
            $query->where('estate_id', $data['estate_id']);
        }
        if (!empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }
        if (!empty($data['allocation_status'])) {
            if ($data['allocation_status'] === 'allocated') {
                $query->whereNotNull('invoice_id');
            } elseif ($data['allocation_status'] === 'unallocated') {
                $query->whereNull('invoice_id');
            }
        }
        if (!empty($data['charge_type_id'])) {
            $query->where('charge_type_id', $data['charge_type_id']);
        }
        if (!empty($data['date_range'])) {
            $query = $this->applyDateRange(
                $query,
                $data['date_range'],
                $data['date_range_start'] ?? null,
                $data['date_range_end'] ?? null,
                'date'
            );
        }
        if (!empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $query->where('description', 'ilike', $term);
        }

        if (!$this->request->has('_sort')) {
            $query->orderBy('date', 'desc');
        }

        $this->setQuery($query);
        $this->applySortOnQuery();

        $limit   = $this->resolveExportLimit($data['_limit'] ?? 'current');
        $entries = $this->query->limit($limit)->get();

        $headings = ['Date', 'Description', 'Type', 'Amount', 'Unit', 'Invoice #', 'Status'];

        $rows = $entries->map(function ($entry) {
            $type   = $entry->type instanceof \BackedEnum ? $entry->type->value : (string) $entry->type;
            $prefix = strtolower($type) === 'credit' ? '+' : '-';
            $status = $entry->invoice_id ? 'Allocated' : 'Unallocated';

            return [
                $entry->date?->format('d M Y'),
                $entry->description,
                ucfirst($type),
                $prefix . number_format((float) $entry->amount, 2),
                $entry->unit?->unit_number ?? '—',
                $entry->invoice?->invoice_number ?? '—',
                $status,
            ];
        })->toArray();

        $format = $data['_format'] ?? 'csv';

        return $this->buildFileResponse(
            $rows,
            $headings,
            'cashbook-' . now()->format('Y-m-d'),
            $format,
            'Cashbook Entries Export',
            ['Generated' => now()->format('d M Y'), 'Records' => count($rows)]
        );
    }

    /**
     * Return aggregate summary statistics for the cashbook.
     *
     * @param array $data
     * @return array
     */
    public function showCashbookSummary(array $data): array
    {
        $user  = Auth::user();
        $query = CashbookEntry::where('tenant_id', $user->tenant_id)
            ->with(['invoice']);

        if (!empty($data['estate_id'])) {
            $query->where('estate_id', $data['estate_id']);
        }

        $stats = (clone $query)->selectRaw(
            'SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as total_credits,
             SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as total_debits,
             COUNT(CASE WHEN invoice_id IS NULL THEN 1 END) as unallocated_count,
             SUM(CASE WHEN invoice_id IS NULL AND type = ? THEN amount ELSE 0 END) as unallocated_amount',
            [
                CashbookEntryType::CREDIT->value,
                CashbookEntryType::DEBIT->value,
                CashbookEntryType::CREDIT->value,
            ]
        )->first();

        $totalCredits       = (float) ($stats->total_credits ?? 0);
        $totalDebits        = (float) ($stats->total_debits ?? 0);

        return [
            'total_credits'      => $totalCredits,
            'total_debits'       => $totalDebits,
            'net_balance'        => $totalCredits - $totalDebits,
            'unallocated_count'  => (int) ($stats->unallocated_count ?? 0),
            'unallocated_amount' => (float) ($stats->unallocated_amount ?? 0),
        ];
    }

    /**
     * Create a new cashbook entry.
     *
     * @param array $data
     * @return array
     */
    public function createCashbookEntry(array $data): array
    {
        $user = Auth::user();

        $entryData = collect($data)
            ->only(['estate_id', 'date', 'type', 'description', 'amount', 'notes', 'unit_id', 'invoice_id', 'charge_type_id'])
            ->toArray();

        if (request()->hasFile('proof_of_payment')) {
            $entryData['proof_of_payment_path'] = request()->file('proof_of_payment')
                ->store("proof_of_payment/{$user->tenant_id}", 'public');
        }

        $entry = CashbookEntry::create(array_merge($entryData, [
            'tenant_id' => $user->tenant_id,
        ]));

        // Auto-update invoice status when the entry is created with an invoice_id
        if (!empty($entryData['invoice_id'])) {
            $this->recalculateInvoiceStatus(Invoice::find($entryData['invoice_id']));
        }

        // Recalculate unit balance whenever a cashbook entry is linked to a unit
        if (!empty($entryData['unit_id'])) {
            $unit = Unit::find($entryData['unit_id']);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }
        }

        return $this->showCreatedResource($entry);
    }

    /**
     * Attempt automatic allocation of unallocated credit entries to outstanding invoices.
     * Phase 1 stub — returns a minimal response. Full implementation in Phase 2.
     *
     * @param array $data
     * @return array
     */
    public function autoAllocateCashbookEntries(array $data): array
    {
        $user    = Auth::user();
        $matched = 0;

        // Phase 1 stub — auto-allocation algorithm to be implemented
        return [
            'matched' => $matched,
            'message' => 'Auto-allocation complete',
        ];
    }

    /**
     * Return a single cashbook entry resource with its relationships loaded.
     *
     * @param CashbookEntry $cashbookEntry
     * @return CashbookEntryResource
     */
    public function showCashbookEntry(CashbookEntry $cashbookEntry): CashbookEntryResource
    {
        $cashbookEntry->load(['estate', 'unit', 'invoice.chargeType', 'chargeType', 'parentEntry']);

        return $this->showResource($cashbookEntry);
    }

    /**
     * Update a cashbook entry's editable fields.
     *
     * @param CashbookEntry $cashbookEntry
     * @param array         $data
     * @return array
     */
    public function updateCashbookEntry(CashbookEntry $cashbookEntry, array $data): array
    {
        $updateData = collect($data)
            ->only(['date', 'type', 'description', 'amount', 'notes', 'unit_id'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $cashbookEntry->update($updateData);

        return $this->showUpdatedResource($cashbookEntry);
    }

    /**
     * Allocate a cashbook entry to an invoice.
     *
     * Handles three scenarios:
     *   1. Exact match — entry amount === invoice outstanding → allocate fully
     *   2. Partial payment — entry amount < invoice outstanding → partially_paid
     *   3. Overpayment / advance — entry amount > invoice outstanding → split entry
     *
     * @param CashbookEntry $cashbookEntry
     * @param array         $data  Must include: invoice_id, unit_id
     * @return array
     * @throws Exception
     */
    public function allocateCashbookEntry(CashbookEntry $cashbookEntry, array $data): array
    {
        if ($cashbookEntry->invoice_id !== null) {
            throw new Exception('This entry has already been allocated to an invoice');
        }

        $invoice  = Invoice::findOrFail($data['invoice_id']);
        $unitId   = $data['unit_id'];

        // Recalculate outstanding (amount minus all currently allocated credits)
        $alreadyPaid  = $invoice->cashbookEntries()->sum('amount');
        $outstanding  = max(0, $invoice->amount - $alreadyPaid);

        if ($outstanding <= 0) {
            throw new Exception('This invoice is already fully paid');
        }

        $entryAmount = $cashbookEntry->amount;

        if ($entryAmount > $outstanding) {
            // OVERPAYMENT / ADVANCE: split the entry into two child entries
            $remainder = $entryAmount - $outstanding;

            // Allocated child — covers the invoice amount exactly
            CashbookEntry::create([
                'estate_id'       => $cashbookEntry->estate_id,
                'date'            => $cashbookEntry->date,
                'type'            => $cashbookEntry->type,
                'description'     => $cashbookEntry->description,
                'amount'          => $outstanding,
                'notes'           => $cashbookEntry->notes,
                'tenant_id'       => $cashbookEntry->tenant_id,
                'unit_id'         => $unitId,
                'invoice_id'      => $invoice->id,
                'charge_type_id'  => $invoice->charge_type_id,
                'parent_entry_id' => $cashbookEntry->id,
            ]);

            // Unallocated remainder — credit on account
            CashbookEntry::create([
                'estate_id'       => $cashbookEntry->estate_id,
                'date'            => $cashbookEntry->date,
                'type'            => $cashbookEntry->type,
                'description'     => $cashbookEntry->description,
                'amount'          => $remainder,
                'notes'           => $cashbookEntry->notes ?? 'Unallocated remainder after allocation to ' . $invoice->invoice_number,
                'tenant_id'       => $cashbookEntry->tenant_id,
                'unit_id'         => $unitId,
                'invoice_id'      => null,
                'charge_type_id'  => null,
                'parent_entry_id' => $cashbookEntry->id,
            ]);

            // Mark original as superseded by deleting it
            $cashbookEntry->delete();

            $this->recalculateInvoiceStatus($invoice->fresh());

            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }

            return [
                'message' => "Entry split: {$outstanding} allocated to {$invoice->invoice_number}, {$remainder} remains as unallocated credit on unit",
                'data'    => null,
            ];
        } elseif ($entryAmount < $outstanding) {
            // PARTIAL PAYMENT: allocate the full entry amount, invoice goes to partially_paid
            $cashbookEntry->update([
                'unit_id'        => $unitId,
                'invoice_id'     => $invoice->id,
                'charge_type_id' => $invoice->charge_type_id,
            ]);

            $this->recalculateInvoiceStatus($invoice->fresh());

            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }

            return [
                'message' => "Partial payment of {$entryAmount} allocated to {$invoice->invoice_number}. Outstanding: " . ($outstanding - $entryAmount),
                'data'    => $this->showResource($cashbookEntry->fresh()),
            ];
        } else {
            // EXACT MATCH: allocate and mark invoice paid
            $cashbookEntry->update([
                'unit_id'        => $unitId,
                'invoice_id'     => $invoice->id,
                'charge_type_id' => $invoice->charge_type_id,
            ]);

            $this->recalculateInvoiceStatus($invoice->fresh());

            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }

            return [
                'message' => "Entry allocated successfully to {$invoice->invoice_number}",
                'data'    => $this->showResource($cashbookEntry->fresh()),
            ];
        }
    }

    /**
     * Deallocate a cashbook entry from its invoice.
     *
     * Clears invoice_id and charge_type_id on the entry, stores the reason in notes,
     * and recalculates the invoice status based on remaining allocated payments.
     *
     * @param CashbookEntry $cashbookEntry
     * @param array         $data  Must include: reason
     * @return array
     * @throws Exception
     */
    public function deallocateCashbookEntry(CashbookEntry $cashbookEntry, array $data): array
    {
        if ($cashbookEntry->invoice_id === null) {
            throw new Exception('This entry is not allocated to any invoice');
        }

        $invoice = Invoice::findOrFail($cashbookEntry->invoice_id);

        $removalNote = '[Payment removed: ' . $data['reason'] . ']';
        $updatedNotes = $cashbookEntry->notes
            ? $cashbookEntry->notes . ' ' . $removalNote
            : $removalNote;

        $unitId = $cashbookEntry->unit_id;

        $cashbookEntry->update([
            'invoice_id'     => null,
            'charge_type_id' => null,
            'notes'          => $updatedNotes,
        ]);

        // Recalculate invoice status based on remaining allocated payments
        $this->recalculateInvoiceStatus($invoice->fresh());

        if ($unitId) {
            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }
        }

        return [
            'message' => 'Payment removed from invoice',
            'data'    => $this->showResource($cashbookEntry->fresh()),
        ];
    }

    /**
     * Recalculate and persist invoice status based on total allocated payments.
     *
     * Paid:          total_paid >= invoice amount
     * Partially paid: 0 < total_paid < invoice amount
     * Unpaid:        total_paid <= 0  (restore original unpaid/overdue state)
     *
     * @param Invoice|null $invoice
     * @return void
     */
    private function recalculateInvoiceStatus(?Invoice $invoice): void
    {
        if (!$invoice) {
            return;
        }

        $totalPaid = (float) $invoice->cashbookEntries()->sum('amount');

        if ($totalPaid >= $invoice->amount) {
            $invoice->update(['status' => InvoiceStatus::PAID->value]);
        } elseif ($totalPaid > 0) {
            $invoice->update(['status' => InvoiceStatus::PARTIALLY_PAID->value]);
        } else {
            // Revert to unpaid (scheduler will re-apply overdue if past due date)
            $invoice->update(['status' => InvoiceStatus::UNPAID->value]);
        }
    }

    /**
     * Upload or replace the proof of payment file for a cashbook entry.
     *
     * @param CashbookEntry $cashbookEntry
     * @param \Illuminate\Http\UploadedFile $file
     * @return CashbookEntryResource
     */
    public function uploadProofOfPayment(CashbookEntry $cashbookEntry, \Illuminate\Http\UploadedFile $file): CashbookEntryResource
    {
        // Delete the existing file if one exists
        if ($cashbookEntry->proof_of_payment_path) {
            Storage::disk('public')->delete($cashbookEntry->proof_of_payment_path);
        }

        $path = $file->store('proof-of-payment', 'public');

        $cashbookEntry->update(['proof_of_payment_path' => $path]);

        return new CashbookEntryResource($cashbookEntry->fresh());
    }

    /**
     * Stream the proof of payment file as a forced download.
     *
     * @param CashbookEntry $cashbookEntry
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws Exception
     */
    public function downloadProofOfPayment(CashbookEntry $cashbookEntry): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (!$cashbookEntry->proof_of_payment_path) {
            throw new Exception('No proof of payment attached to this entry.');
        }

        $filename = basename($cashbookEntry->proof_of_payment_path);

        return Storage::disk('public')->download($cashbookEntry->proof_of_payment_path, $filename);
    }

    /**
     * Delete the proof of payment file from a cashbook entry.
     *
     * @param CashbookEntry $cashbookEntry
     * @return array
     */
    public function deleteProofOfPayment(CashbookEntry $cashbookEntry): array
    {
        if ($cashbookEntry->proof_of_payment_path) {
            Storage::disk('public')->delete($cashbookEntry->proof_of_payment_path);
            $cashbookEntry->update(['proof_of_payment_path' => null]);
        }

        return ['message' => 'Proof of payment removed'];
    }

    /**
     * Bulk delete cashbook entries by an array of IDs.
     *
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteCashbookEntries(array $ids): array
    {
        $user    = Auth::user();
        $entries = CashbookEntry::whereIn('id', $ids)
            ->where('tenant_id', $user->tenant_id)
            ->get();

        $total = $entries->count();

        if ($total === 0) {
            throw new Exception('No Cashbook Entries deleted');
        }

        $affectedUnitIds = $entries->pluck('unit_id')->filter()->unique()->values();
        $invoiceIds      = $entries->pluck('invoice_id')->filter()->unique()->values();

        foreach ($entries as $entry) {
            $entry->delete();
        }

        // Recalculate invoice statuses for all affected invoices
        foreach ($invoiceIds as $invoiceId) {
            $this->recalculateInvoiceStatus(Invoice::find($invoiceId));
        }

        // Recalculate unit balances for all affected units
        foreach ($affectedUnitIds as $unitId) {
            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }
        }

        $label = $total === 1 ? 'Cashbook Entry' : 'Cashbook Entries';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single cashbook entry.
     *
     * @param CashbookEntry $cashbookEntry
     * @return array
     */
    public function deleteCashbookEntry(CashbookEntry $cashbookEntry): array
    {
        $unitId    = $cashbookEntry->unit_id;
        $invoiceId = $cashbookEntry->invoice_id;
        $deleted   = $cashbookEntry->delete();

        if ($deleted) {
            if ($invoiceId) {
                $this->recalculateInvoiceStatus(Invoice::find($invoiceId));
            }
            if ($unitId) {
                $unit = Unit::find($unitId);
                if ($unit) {
                    $this->unitBalance->recalculate($unit);
                }
            }
        }

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Cashbook Entry deleted' : 'Cashbook Entry delete unsuccessful',
        ];
    }
}
