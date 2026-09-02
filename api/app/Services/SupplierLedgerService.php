<?php

namespace App\Services;

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Models\JournalLine;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for a supplier's (creditor's) ledger, mirroring
 * WeConnectU. Every supplier transaction posts a SUPPLIER journal line to the
 * General Ledger (Accounts Payable subledger) — a supplier invoice (GRV) posts a
 * Cr, a bank payment allocated to the supplier posts a Dr, and a manual journal
 * posts either — so the Detailed Supplier Ledger, the Supplier Age Analysis
 * drill-down and the stored supplier balance all read the GL exclusively and a
 * posting reflects identically everywhere.
 *
 * Sign convention (WeConnectU): a ledger DEBIT reduces what the community owes
 * the supplier (a payment to them); a ledger CREDIT increases it (an invoice
 * from them). The running Cumulative and the stored balance are debit − credit,
 * so a credit-heavy (money-owed) supplier shows a NEGATIVE balance.
 */
class SupplierLedgerService
{
    /** Bucket keys, oldest → newest. */
    public const BUCKETS = ['120_plus', '90_days', '60_days', '30_days', 'current'];

    /**
     * All normalised ledger events for the given supplier(s), sorted by date
     * ascending. Each row: supplier_id, date (Y-m-d), source, description,
     * remarks, debit, credit, and (for GRVs) grv => [id, number].
     *
     * @param array<string>|string $supplierIds
     * @param string|null $communityId Restrict to a single community's transactions.
     * @return Collection<int, array>
     */
    public function events(array|string $supplierIds, ?string $communityId = null): Collection
    {
        $ids = array_values(array_filter((array) $supplierIds));

        if (empty($ids)) {
            return collect();
        }

        return $this->supplierLines($ids, $communityId)
            ->map(fn (JournalLine $l) => $this->normaliseLine($l))
            ->filter(fn ($e) => $e !== null && ! empty($e['date']))
            ->sortBy('date')
            ->values();
    }

    /**
     * The SUPPLIER journal lines (Accounts Payable subledger) for the given
     * supplier(s), across every source, with the batch + its originating document
     * eager-loaded for the Source column.
     *
     * @param array<string> $ids
     * @param string|null $communityId
     * @return Collection<int, JournalLine>
     */
    private function supplierLines(array $ids, ?string $communityId): Collection
    {
        return JournalLine::whereIn('supplier_id', $ids)
            ->where('line_type', JournalLineType::SUPPLIER)
            ->when($communityId, fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('community_id', $communityId)))
            ->with(['batch:id,batch_number,date,source,source_type,source_id', 'batch.sourceDocument'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (JournalLine $l) => $l->batch !== null)
            ->values();
    }

    /**
     * Normalise a single SUPPLIER journal line into a ledger event row.
     *
     * @param JournalLine $l
     * @return array<string, mixed>|null
     */
    private function normaliseLine(JournalLine $l): ?array
    {
        $batch   = $l->batch;
        $isDebit = $l->entry_type === JournalEntryType::DEBIT;
        $source  = $this->sourceLabel($l);

        // Blue GRV link, when this line came from a supplier invoice.
        $grv     = null;
        $remarks = '';
        if ($batch->source === \App\Enums\JournalSource::SUPPLIER_INVOICE) {
            $inv = $batch->sourceDocument;
            if ($inv instanceof \App\Models\SupplierInvoice) {
                $grv     = ['id' => $inv->id, 'number' => $inv->grv_number];
                $remarks = (string) ($inv->description ?? '');
            }
        } elseif ($batch->source === \App\Enums\JournalSource::CASHBOOK) {
            $entry = $batch->sourceDocument;
            if ($entry instanceof \App\Models\CashbookEntry) {
                $remarks = (string) ($entry->allocation_remarks ?? '');
            }
        }

        return [
            'supplier_id' => $l->supplier_id,
            'date'        => optional($batch->date)->toDateString(),
            'source'      => $source,
            'description' => $grv ? $this->grvLabel($batch->sourceDocument) : (string) ($l->description ?? ''),
            'remarks'     => $remarks,
            'debit'       => $isDebit ? (float) $l->amount : 0.0,
            'credit'      => ! $isDebit ? (float) $l->amount : 0.0,
            'grv'         => $grv,
        ];
    }

    /**
     * The WeConnectU "Source" column label for a supplier journal line: a manual
     * batch shows "Journal Batch N"; a cashbook payment shows the bank ("STANDARD
     * BANK: 401794555"); a supplier invoice shows "Supplier Invoice".
     *
     * @param JournalLine $l
     * @return string
     */
    private function sourceLabel(JournalLine $l): string
    {
        $batch = $l->batch;

        if ($batch->batch_number) {
            return 'Journal Batch ' . $batch->batch_number;
        }

        if ($batch->source === \App\Enums\JournalSource::CASHBOOK) {
            $entry = $batch->sourceDocument;
            $bank  = $entry instanceof \App\Models\CashbookEntry ? $entry->bankAccount : null;
            if ($bank) {
                return strtoupper((string) $bank->bank_name) . ': ' . $bank->account_number;
            }
            return 'Cashbook';
        }

        return $batch->source instanceof \App\Enums\JournalSource
            ? $batch->source->label()
            : 'Journal';
    }

    /**
     * Build one supplier's Detailed-Ledger table (Date · Source · Description ·
     * Remarks · Debit · Credit · Cumulative) with a per-supplier totals row,
     * optionally bounded to on/before an "as at" date.
     *
     * @param Supplier $supplier
     * @param string|null $asAt
     * @param string|null $communityId
     * @return array{heading:string, rows:array, totals:array}
     */
    public function ledgerFor(Supplier $supplier, ?string $asAt = null, ?string $communityId = null): array
    {
        $events = $this->events($supplier->id, $communityId);
        if ($asAt) {
            $events = $events->filter(fn ($e) => $e['date'] <= $asAt)->values();
        }

        $cumulative  = 0.0;
        $debitTotal  = 0.0;
        $creditTotal = 0.0;
        $rows        = [];

        foreach ($events as $e) {
            $cumulative  += $e['debit'] - $e['credit'];
            $debitTotal  += $e['debit'];
            $creditTotal += $e['credit'];
            $rows[]       = [
                'date'        => $e['date'],
                'source'      => $e['source'],
                'description' => $e['description'],
                'remarks'     => $e['remarks'],
                'debit'       => round($e['debit'], 2),
                'credit'      => round($e['credit'], 2),
                'cumulative'  => round($cumulative, 2),
                'grv'         => $e['grv'],
            ];
        }

        return [
            'heading' => trim(($supplier->supplier_code ? $supplier->supplier_code . ': ' : '') . $supplier->name),
            'rows'    => $rows,
            'totals'  => [
                'debit'      => round($debitTotal, 2),
                'credit'     => round($creditTotal, 2),
                'cumulative' => round($cumulative, 2),
            ],
        ];
    }

    /**
     * Aged buckets + net balance per supplier, as at a date.
     *
     * WeConnectU nets the ledger oldest-first and ages whatever remains by its
     * transaction date: if the supplier is net debit (positive balance) the
     * leftover DEBITS are aged as positive amounts; if net credit (negative
     * balance) the leftover CREDITS are aged as negative amounts.
     *
     * @param array<string> $supplierIds
     * @param string $asAt
     * @param string|null $communityId
     * @return array<string, array{buckets: array<string,float>, balance: float}>
     */
    public function agedBySupplier(array $supplierIds, string $asAt, ?string $communityId = null): array
    {
        $ageing = Carbon::parse($asAt)->startOfDay();
        $byId   = $this->events($supplierIds, $communityId)
            ->filter(fn ($e) => $e['date'] <= $asAt)
            ->groupBy('supplier_id');

        $out = [];
        foreach ($byId as $supplierId => $events) {
            $out[$supplierId] = $this->ageEvents($events, $ageing);
        }

        return $out;
    }

    /**
     * Recalculate and persist a supplier's stored balance (debit − credit across
     * every ledger source). Call after posting/deleting a GRV.
     *
     * @param Supplier $supplier
     * @return float
     */
    public function recalculate(Supplier $supplier): float
    {
        $net = (float) $this->events($supplier->id)
            ->sum(fn ($e) => $e['debit'] - $e['credit']);

        $net = round($net, 2);
        $supplier->update(['balance' => $net]);

        return $net;
    }

    /**
     * Net-and-age a supplier's events into the WeConnectU buckets.
     *
     * @param Collection<int, array> $events
     * @param Carbon $ageing
     * @return array{buckets: array<string,float>, balance: float}
     */
    private function ageEvents(Collection $events, Carbon $ageing): array
    {
        $buckets = array_fill_keys(self::BUCKETS, 0.0);

        // Split into dated debit / credit legs, oldest first.
        $debits = $events->filter(fn ($e) => $e['debit'] > 0)
            ->map(fn ($e) => ['date' => $e['date'], 'amount' => (float) $e['debit']])
            ->sortBy('date')->values();
        $credits = $events->filter(fn ($e) => $e['credit'] > 0)
            ->map(fn ($e) => ['date' => $e['date'], 'amount' => (float) $e['credit']])
            ->sortBy('date')->values();

        $totalDebit  = round($debits->sum('amount'), 2);
        $totalCredit = round($credits->sum('amount'), 2);
        $balance     = round($totalDebit - $totalCredit, 2);

        if (abs($balance) < 0.005) {
            return ['buckets' => $buckets, 'balance' => 0.0];
        }

        if ($balance > 0) {
            // Net debit: remove the credits from the oldest debits, age the rest (+).
            foreach ($this->consumeFifo($debits, $totalCredit) as $leg) {
                $buckets[$this->bucketFor($leg['date'], $ageing)] += $leg['amount'];
            }
        } else {
            // Net credit: remove the debits from the oldest credits, age the rest (−).
            foreach ($this->consumeFifo($credits, $totalDebit) as $leg) {
                $buckets[$this->bucketFor($leg['date'], $ageing)] -= $leg['amount'];
            }
        }

        foreach ($buckets as $k => $v) {
            $buckets[$k] = round($v, 2);
        }

        return ['buckets' => $buckets, 'balance' => $balance];
    }

    /**
     * Remove $amount from a date-sorted list of legs, oldest first, returning the
     * legs (with reduced amounts) that survive.
     *
     * @param Collection<int, array{date:string, amount:float}> $legs
     * @param float $amount
     * @return array<int, array{date:string, amount:float}>
     */
    private function consumeFifo(Collection $legs, float $amount): array
    {
        $remaining = round($amount, 2);
        $out       = [];

        foreach ($legs as $leg) {
            $legAmount = (float) $leg['amount'];

            if ($remaining <= 0) {
                $out[] = ['date' => $leg['date'], 'amount' => round($legAmount, 2)];
                continue;
            }

            if ($remaining >= $legAmount) {
                $remaining = round($remaining - $legAmount, 2);
                continue; // fully consumed
            }

            $out[]     = ['date' => $leg['date'], 'amount' => round($legAmount - $remaining, 2)];
            $remaining = 0.0;
        }

        return $out;
    }

    /**
     * The ageing bucket for a transaction date, by its age in days as at the
     * ageing date: 0–30 current, 31–60 30, 61–90 60, 91–120 90, 120+.
     *
     * @param string $date
     * @param Carbon $ageing
     * @return string
     */
    private function bucketFor(string $date, Carbon $ageing): string
    {
        $days = (int) Carbon::parse($date)->startOfDay()->diffInDays($ageing, false);
        // diffInDays(false): future dates negative; a transaction on/before the
        // ageing date yields a non-negative age.
        $days = abs($days);

        return match (true) {
            $days <= 30  => 'current',
            $days <= 60  => '30_days',
            $days <= 90  => '60_days',
            $days <= 120 => '90_days',
            default      => '120_plus',
        };
    }

    /**
     * The blue GRV link label shown in the ledger Description column, e.g.
     * "GRV00030 : 15417 / 15417" or "GRV00005 : 9310".
     *
     * @param \App\Models\SupplierInvoice $inv
     * @return string
     */
    private function grvLabel(\App\Models\SupplierInvoice $inv): string
    {
        $refs = array_values(array_filter([
            $inv->source_document_number,
            $inv->supplier_reference,
        ], fn ($v) => $v !== null && $v !== ''));

        $tail = empty($refs) ? '' : ' : ' . implode(' / ', $refs);

        return $inv->grv_number . $tail;
    }
}
