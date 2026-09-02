<?php

namespace App\Services;

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Enums\JournalSource;
use App\Models\Invoice;
use App\Models\JournalLine;
use Illuminate\Support\Collection;

/**
 * Balance-forward customer accounting, exactly like WeConnectU. A customer's
 * account is the running position on the Accounts Receivable control account
 * (journal_lines with line_type=customer). Invoices are the AR DEBITS; receipts
 * and credit notes are the AR CREDITS. Nothing is "open item"-linked — instead
 * an invoice's paid / outstanding amount is DERIVED by applying every credit to
 * the oldest debits first (FIFO), so a payment naturally settles the earliest
 * arrears.
 *
 * This retires the old open-item `cashbook_entries.invoice_id` reliance for
 * invoice paid/outstanding: those figures now come from the GL.
 */
class CustomerAccountService
{
    /**
     * Per-unit FIFO application cache for the current request, keyed by unit id.
     * Value: [invoiceId => paidAmount] for AR debits sourced from invoices.
     *
     * @var array<string, array<string, float>>
     */
    private array $paidByInvoiceCache = [];

    /**
     * Total amount applied (paid) against an invoice, derived FIFO from the GL.
     *
     * @param Invoice $invoice
     * @return float
     */
    public function totalPaidForInvoice(Invoice $invoice): float
    {
        if (! $invoice->unit_id) {
            return 0.0;
        }

        $paid = $this->paidByInvoice($invoice->unit_id);

        return round((float) ($paid[$invoice->id] ?? 0.0), 2);
    }

    /**
     * Outstanding amount still owed on an invoice (never negative).
     *
     * @param Invoice $invoice
     * @return float
     */
    public function outstandingForInvoice(Invoice $invoice): float
    {
        return round(max(0.0, (float) $invoice->amount - $this->totalPaidForInvoice($invoice)), 2);
    }

    /**
     * Whether an invoice is fully settled (outstanding rounds to zero).
     *
     * @param Invoice $invoice
     * @return bool
     */
    public function isPaid(Invoice $invoice): bool
    {
        return $this->outstandingForInvoice($invoice) <= 0.0 && (float) $invoice->amount > 0.0;
    }

    /**
     * Drop the cached FIFO application for a unit (or all units), forcing the
     * next derivation to re-read the GL. Useful after posting a new batch within
     * the same request.
     *
     * @param string|null $unitId
     * @return void
     */
    public function forget(?string $unitId = null): void
    {
        if ($unitId === null) {
            $this->paidByInvoiceCache = [];
            return;
        }

        unset($this->paidByInvoiceCache[$unitId]);
    }

    /**
     * Apply every AR credit for a unit against its invoice debits oldest-first
     * (FIFO), returning [invoiceId => amountPaid]. Cached per request.
     *
     * Debits are keyed back to their source Invoice via the batch source
     * (source=invoice, source_type/source_id). Credits (receipts + credit notes)
     * are consumed against the debits ordered by batch date then invoice due date.
     *
     * @param string $unitId
     * @return array<string, float>
     */
    private function paidByInvoice(string $unitId): array
    {
        if (array_key_exists($unitId, $this->paidByInvoiceCache)) {
            return $this->paidByInvoiceCache[$unitId];
        }

        $lines = JournalLine::query()
            ->where('unit_id', $unitId)
            ->where('line_type', JournalLineType::CUSTOMER)
            ->with('batch:id,date,source,source_type,source_id')
            ->get()
            ->filter(fn (JournalLine $l) => $l->batch !== null);

        // Roll each batch up to a single AR movement (an invoice batch has one
        // customer debit per line item — sum them so the invoice is one debit).
        $invoiceDebits = [];   // [invoiceId => ['date'=>, 'due'=>, 'amount'=>]]
        $totalCredits  = 0.0;

        foreach ($lines as $line) {
            $batch  = $line->batch;
            $amount = (float) $line->amount;

            if ($line->entry_type === JournalEntryType::CREDIT) {
                $totalCredits += $amount;
                continue;
            }

            // A customer DEBIT keyed to an invoice contributes to that invoice's
            // debit; any other debit (manual journal etc.) is not FIFO-target.
            $isInvoice = $batch->source === JournalSource::INVOICE
                && $batch->source_id !== null;

            if (! $isInvoice) {
                continue;
            }

            $invoiceId = $batch->source_id;
            $invoiceDebits[$invoiceId] ??= [
                'date'   => optional($batch->date)->toDateString() ?? '',
                'due'    => optional($line->due_date)->toDateString() ?? '',
                'amount' => 0.0,
            ];
            $invoiceDebits[$invoiceId]['amount'] += $amount;
        }

        // FIFO order: oldest batch date, then earliest due date.
        uasort($invoiceDebits, function (array $a, array $b): int {
            return [$a['date'], $a['due']] <=> [$b['date'], $b['due']];
        });

        $paid      = [];
        $remaining = round($totalCredits, 2);

        foreach ($invoiceDebits as $invoiceId => $debit) {
            if ($remaining <= 0) {
                $paid[$invoiceId] = 0.0;
                continue;
            }

            $applied          = min($remaining, round($debit['amount'], 2));
            $paid[$invoiceId] = $applied;
            $remaining        = round($remaining - $applied, 2);
        }

        return $this->paidByInvoiceCache[$unitId] = $paid;
    }
}
