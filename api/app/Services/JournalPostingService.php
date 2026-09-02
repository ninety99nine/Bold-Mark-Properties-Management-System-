<?php

namespace App\Services;

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Models\JournalLine;
use Illuminate\Support\Collection;

/**
 * Single source of truth for how a customer-ledger journal line posts to a
 * customer's account. Every place that reads a customer's transactions or
 * balance — the stored unit balance, the Detailed Customer Ledger, the customer
 * statement (screen / Excel / PDF), Age Analysis and Status Management — pulls
 * journal activity through this service so a journal reflects identically
 * everywhere, exactly like WeConnectU.
 *
 * A customer-ledger DEBIT increases what the customer owes; a CREDIT reduces it.
 */
class JournalPostingService
{
    /**
     * All customer journal entries for the given unit(s), normalised and sorted
     * by date ascending. Each row: unit_id, date (Y-m-d), due_date (Y-m-d|null),
     * source (label), description, debit, credit.
     *
     * This is the complete customer subledger: every customer document — invoice,
     * credit note, cashbook allocation — and every manual journal posts a
     * CUSTOMER journal line, and every customer read-service (the stored unit
     * balance, the Detailed Customer Ledger, the customer statement, Age Analysis
     * and Status Management) builds itself from this method alone, so a posting
     * reflects identically everywhere, exactly like WeConnectU.
     *
     * @param array<string>|string $unitIds
     * @return Collection<int, array>
     */
    public function customerEntries(array|string $unitIds): Collection
    {
        $ids = array_values(array_filter((array) $unitIds));

        if (empty($ids)) {
            return collect();
        }

        return JournalLine::whereIn('unit_id', $ids)
            ->where('line_type', JournalLineType::CUSTOMER)
            ->with('batch:id,batch_number,date,source')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (JournalLine $l) => $l->batch !== null)
            ->map(function (JournalLine $l) {
                $isDebit = $l->entry_type === JournalEntryType::DEBIT;
                $batch   = $l->batch;

                // Manual batches show "Journal Batch N"; auto-postings (no batch
                // number) show their source label (Invoice, Cashbook, …).
                $source = $batch->batch_number
                    ? 'Journal Batch ' . $batch->batch_number
                    : ($batch->source instanceof \App\Enums\JournalSource ? $batch->source->label() : 'Journal');

                return [
                    'unit_id'     => $l->unit_id,
                    'batch_id'    => $l->journal_batch_id,
                    'date'        => optional($batch->date)->toDateString(),
                    'due_date'    => optional($l->due_date)->toDateString(),
                    'source'      => $source,
                    'description' => (string) ($l->description ?? ''),
                    'debit'       => $isDebit ? (float) $l->amount : 0.0,
                    'credit'      => ! $isDebit ? (float) $l->amount : 0.0,
                ];
            })
            ->sortBy('date')
            ->values();
    }

    /**
     * Normalised journal events for a single unit, optionally within [from, to].
     *
     * @return array<int, array>
     */
    public function eventsForUnit(string $unitId, ?string $from = null, ?string $to = null): array
    {
        return $this->customerEntries([$unitId])
            ->filter(function ($e) use ($from, $to) {
                if ($from && ($e['date'] ?? '') < $from) {
                    return false;
                }
                if ($to && ($e['date'] ?? '') > $to) {
                    return false;
                }
                return true;
            })
            ->values()
            ->all();
    }

    /**
     * Opening effect (debit − credit) for journal lines strictly before $from.
     * Zero when no from-date is supplied.
     */
    public function openingBefore(string $unitId, ?string $from): float
    {
        if (! $from) {
            return 0.0;
        }

        return (float) $this->customerEntries([$unitId])
            ->filter(fn ($e) => ($e['date'] ?? '') < $from)
            ->sum(fn ($e) => $e['debit'] - $e['credit']);
    }

    /**
     * Effect on a unit's stored balance: credit − debit (a credit is a
     * credit-on-account that reduces arrears, matching UnitBalanceService's sign).
     */
    public function balanceEffect(string $unitId): float
    {
        return (float) $this->customerEntries([$unitId])
            ->sum(fn ($e) => $e['credit'] - $e['debit']);
    }

    /**
     * Aged customer subledger per unit as at a date, for the Age Analysis /
     * Status buckets. Only lines dated on/before $asAt are considered (so a past
     * ageing date reflects the balances as they were then).
     *
     * A customer DEBIT (an invoice line, a manual charge) is aged by its
     * **due_date** — falling back to the batch date when no due date is carried —
     * so arrears age from when they became due, exactly like WeConnectU. A
     * customer CREDIT (a receipt allocated to the customer, a credit note) is
     * returned as an oldest-first lump the caller nets across the buckets.
     *
     * @param array<string> $unitIds
     * @return array<string, array{credit: float, debits: array<int, array{date: string, amount: float}>}>
     */
    public function agedCustomerByUnit(array $unitIds, string $asAt): array
    {
        $out = [];

        foreach ($this->customerEntries($unitIds) as $e) {
            if (($e['date'] ?? '') > $asAt) {
                continue;
            }

            $uid       = $e['unit_id'];
            $out[$uid] ??= ['credit' => 0.0, 'debits' => []];

            if ($e['credit'] > 0) {
                $out[$uid]['credit'] += $e['credit'];
            }
            if ($e['debit'] > 0) {
                // Age the debit by its due date; fall back to the batch date.
                $ageDate = $e['due_date'] ?: $e['date'];
                $out[$uid]['debits'][] = ['date' => $ageDate, 'amount' => $e['debit']];
            }
        }

        return $out;
    }
}
