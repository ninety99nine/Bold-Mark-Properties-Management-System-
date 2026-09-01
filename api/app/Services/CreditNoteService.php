<?php

namespace App\Services;

use App\Enums\BilledToType;
use App\Jobs\SendCreditNoteEmail;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\CreditNoteResources;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Unit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Facades\Resend;

class CreditNoteService extends BaseService
{
    protected array $allowedRelationships = ['unit', 'appliedInvoice', 'items', 'billedToOwner', 'billedToUnitOccupant'];

    public function __construct(private readonly UnitBalanceService $unitBalance)
    {
        parent::__construct();
    }

    /**
     * Return a paginated, filtered list of credit notes for the organization.
     *
     * @param array $data
     * @return CreditNoteResources
     */
    public function showCreditNotes(array $data): CreditNoteResources
    {
        $user  = Auth::user();
        $query = CreditNote::where('organization_id', $user->organization_id)
            ->with(['unit.community', 'appliedInvoice', 'billedToOwner', 'billedToUnitOccupant']);

        if (!empty($data['country'])) {
            $query->whereHas('unit.community', fn ($q) => $q->where('country', $data['country']));
        }

        if (!empty($data['community_id'])) {
            $query->whereHas('unit', fn ($q) => $q->where('community_id', $data['community_id']));
        }

        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }

        if (!empty($data['billed_to_type'])) {
            $query->where('billed_to_type', $data['billed_to_type']);
        }

        if (!empty($data['billed_to_id'])) {
            $query->where('billed_to_id', $data['billed_to_id']);
        }

        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('credit_note_number', $search)
                  ->orWhereHas('unit', fn ($u) => $u->whereLike('unit_number', $search))
                  ->orWhere(function ($sub) use ($search) {
                      $sub->where('billed_to_type', 'owner')
                          ->whereHas('billedToOwner', fn ($o) => $o->whereLike('full_name', $search));
                  })
                  ->orWhere(function ($sub) use ($search) {
                      $sub->where('billed_to_type', 'occupant')
                          ->whereHas('billedToUnitOccupant', fn ($t) => $t->whereLike('full_name', $search));
                  });
            });
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Return the last 20 invoices for a customer (unit), each normalised into a
     * list of creditable line items. Powers the "Does this credit note apply to a
     * specific invoice?" flow — WeConnectU shows the invoice's lines with tick
     * boxes so the user can copy them onto the credit note.
     *
     * A manual (multi-line) invoice exposes its invoice_items; a system /
     * single-charge invoice exposes one synthetic line built from its header
     * ledger and amount.
     *
     * @param array $data
     * @return array
     */
    public function showCreditableInvoices(array $data): array
    {
        $user = Auth::user();

        $unit = Unit::where('id', $data['unit_id'])
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $invoices = Invoice::where('unit_id', $unit->id)
            ->where('organization_id', $user->organization_id)
            ->with(['ledger', 'items.ledger'])
            ->latest('invoice_date')
            ->latest('created_at')
            ->limit(20)
            ->get();

        $data = $invoices->map(function (Invoice $invoice) {
            $date = $invoice->invoice_date ?? $invoice->billing_period ?? $invoice->created_at;

            if ($invoice->items->isNotEmpty()) {
                $lines = $invoice->items->map(fn ($item) => [
                    'ledger_id'   => $item->ledger_id,
                    'account'     => $item->ledger?->name ?? 'Account',
                    'description' => $item->description ?: ($item->ledger?->name ?? ''),
                    'tax_type'    => $item->tax_rate > 0 ? 'VAT' : 'VAT Not Applicable',
                    'quantity'    => (float) $item->quantity,
                    'unit_price'  => (float) $item->amount,
                    'tax_rate'    => (float) $item->tax_rate,
                    'tax'         => (float) $item->tax_amount,
                    'total'       => (float) $item->line_total,
                ])->values()->all();
            } else {
                $lines = [[
                    'ledger_id'   => $invoice->ledger_id,
                    'account'     => $invoice->ledger?->name ?? 'Account',
                    'description' => $invoice->ledger?->name ?? '',
                    'tax_type'    => 'VAT Not Applicable',
                    'quantity'    => 1.0,
                    'unit_price'  => (float) $invoice->amount,
                    'tax_rate'    => 0.0,
                    'tax'         => 0.0,
                    'total'       => (float) $invoice->amount,
                ]];
            }

            return [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date'   => $date?->toDateString(),
                'amount'         => (float) $invoice->amount,
                'lines'          => $lines,
            ];
        })->values()->all();

        return ['data' => $data];
    }

    /**
     * Create a WeConnectU-style multi-line credit note.
     *
     * The header caches the rolled-up subtotal / VAT / total, while each account
     * line is stored as a credit_note_item. A credit note credits the customer's
     * account, so the unit balance is recalculated after it is written. It is
     * billed to the unit's primary owner, falling back to the current occupant.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createCreditNote(array $data): array
    {
        $user = Auth::user();

        $unit = Unit::where('id', $data['unit_id'])
            ->where('organization_id', $user->organization_id)
            ->with(['owner', 'currentOccupant'])
            ->firstOrFail();

        [$billedToType, $billedToId] = $this->resolveBilledTo($unit);

        if (!$billedToType || !$billedToId) {
            throw new Exception('This unit has no owner or occupant to credit.');
        }

        // Validate the applied invoice (when supplied) belongs to this unit + org.
        if (!empty($data['applied_invoice_id'])) {
            Invoice::where('id', $data['applied_invoice_id'])
                ->where('organization_id', $user->organization_id)
                ->where('unit_id', $unit->id)
                ->firstOrFail();
        }

        $creditNoteDate = Carbon::parse($data['credit_note_date']);

        // Roll up line-item totals.
        $subtotal = 0.0;
        $vatTotal = 0.0;
        $items    = [];

        foreach (array_values($data['items']) as $index => $row) {
            $quantity = (float) $row['quantity'];
            $unitAmt  = (float) $row['amount'];
            $taxRate  = (float) ($row['tax_rate'] ?? 0);

            $lineSubtotal = round($quantity * $unitAmt, 2);
            $taxAmount    = round($lineSubtotal * $taxRate / 100, 2);
            $lineTotal    = round($lineSubtotal + $taxAmount, 2);

            $subtotal += $lineSubtotal;
            $vatTotal += $taxAmount;

            $items[] = [
                'ledger_id'   => $row['ledger_id'],
                'description' => $row['description'] ?? null,
                'quantity'    => $quantity,
                'amount'      => $unitAmt,
                'tax_rate'    => $taxRate,
                'tax_amount'  => $taxAmount,
                'line_total'  => $lineTotal,
                'sort_order'  => $index,
            ];
        }

        $subtotal   = round($subtotal, 2);
        $vatTotal   = round($vatTotal, 2);
        $grandTotal = round($subtotal + $vatTotal, 2);

        $creditNote = DB::transaction(function () use (
            $user, $unit, $data, $billedToType, $billedToId,
            $creditNoteDate, $subtotal, $vatTotal, $grandTotal, $items
        ) {
            $creditNote = CreditNote::create([
                'unit_id'            => $unit->id,
                'applied_invoice_id' => $data['applied_invoice_id'] ?? null,
                'billed_to_type'     => $billedToType,
                'billed_to_id'       => $billedToId,
                'reason'             => $data['reason'] ?? null,
                'order_no'           => $data['order_no'] ?? null,
                'reference'          => $data['reference'] ?? null,
                'amount'             => $grandTotal,
                'subtotal'           => $subtotal,
                'vat_amount'         => $vatTotal,
                'credit_note_date'   => $creditNoteDate->format('Y-m-d'),
                'credit_note_number' => $this->generateCreditNoteNumber($user->organization_id),
                'organization_id'    => $user->organization_id,
                'issued_by_type'     => 'user',
                'issued_by_user_id'  => $user->id,
            ]);

            foreach ($items as $item) {
                $creditNote->items()->create($item);
            }

            return $creditNote;
        });

        $this->unitBalance->recalculate($unit);

        if (!empty($data['email_credit_note'])) {
            SendCreditNoteEmail::dispatch($creditNote->id);
        }

        $creditNote->load([
            'unit.community', 'appliedInvoice', 'items.ledger',
            'billedToOwner', 'billedToUnitOccupant',
        ]);

        return $this->showCreatedResource($creditNote);
    }

    /**
     * Resolve the billed-to entity for a credit note: primary owner first, then
     * the current occupant.
     *
     * @param Unit $unit
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveBilledTo(Unit $unit): array
    {
        if ($unit->owner) {
            return [BilledToType::OWNER->value, $unit->owner->id];
        }

        if ($unit->currentOccupant) {
            return [BilledToType::OCCUPANT->value, $unit->currentOccupant->id];
        }

        return [null, null];
    }

    /**
     * Return a single credit note resource with its relationships loaded.
     *
     * @param CreditNote $creditNote
     * @return CreditNoteResource
     */
    public function showCreditNote(CreditNote $creditNote): CreditNoteResource
    {
        $creditNote->load(['unit.community', 'appliedInvoice', 'items.ledger', 'billedToOwner', 'billedToUnitOccupant', 'issuedBy']);

        return $this->showResource($creditNote);
    }

    /**
     * Send (or resend) the credit note email via Resend.
     *
     * @param CreditNote $creditNote
     * @return array
     * @throws Exception
     */
    public function sendCreditNote(CreditNote $creditNote): array
    {
        $creditNote->load(['unit.community', 'items.ledger', 'billedToOwner', 'billedToUnitOccupant']);

        $billedTo = $creditNote->billed_to_type->value === BilledToType::OWNER->value
            ? $creditNote->billedToOwner
            : $creditNote->billedToUnitOccupant;

        if (!$billedTo || !$billedTo->email) {
            throw new Exception('No email address found for the credit note recipient.');
        }

        $html    = view('emails.credit-note', ['creditNote' => $creditNote, 'billedTo' => $billedTo])->render();
        $from    = config('mail.from.name') . ' <' . config('mail.from.address') . '>';
        $subject = "Credit Note {$creditNote->credit_note_number}";

        if (app()->isLocal()) {
            Log::info("[local] Credit note email suppressed — would send to {$billedTo->email}", [
                'credit_note' => $creditNote->credit_note_number,
                'subject'     => $subject,
            ]);
        } else {
            Resend::emails()->send([
                'from'    => $from,
                'to'      => [$billedTo->email],
                'subject' => $subject,
                'html'    => $html,
            ]);
        }

        $creditNote->update(['sent_at' => now()]);

        return ['message' => 'Credit note sent successfully'];
    }

    /**
     * Generate and stream a branded PDF for this credit note.
     *
     * @param CreditNote $creditNote
     * @return Response
     */
    public function downloadPdf(CreditNote $creditNote): Response
    {
        $creditNote->load(['unit.community', 'appliedInvoice', 'items.ledger', 'billedToOwner', 'billedToUnitOccupant']);

        $billedTo = $creditNote->billed_to_type->value === BilledToType::OWNER->value
            ? $creditNote->billedToOwner
            : $creditNote->billedToUnitOccupant;

        $organization = $creditNote->organization;

        $pdf = Pdf::loadView('pdfs.credit-note', [
            'creditNote'      => $creditNote,
            'billedTo'        => $billedTo,
            'organization'    => $organization,
            'companyLogoPath' => $organization?->logoFilePath(),
        ])->setPaper('a4');

        return $pdf->download("{$creditNote->credit_note_number}.pdf");
    }

    /**
     * Delete a single credit note and recalculate the unit balance.
     *
     * @param CreditNote $creditNote
     * @return array
     */
    public function deleteCreditNote(CreditNote $creditNote): array
    {
        $unit    = $creditNote->unit;
        $deleted = $creditNote->delete();

        if ($deleted) {
            $this->unitBalance->recalculate($unit);
        }

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Credit note deleted' : 'Credit note delete unsuccessful',
        ];
    }

    /**
     * Generate a sequential credit note number for the organization.
     * Format: CN-{YEAR}-{ZERO_PADDED_COUNT}
     *
     * @param string $organizationId
     * @return string
     */
    private function generateCreditNoteNumber(string $organizationId): string
    {
        $year   = date('Y');
        $prefix = 'CN-' . $year . '-';

        $max = CreditNote::where('organization_id', $organizationId)
            ->where('credit_note_number', 'like', $prefix . '%')
            ->withTrashed()
            ->max('credit_note_number');

        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
