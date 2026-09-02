<?php

namespace App\Services;

use App\Enums\CollectionStatus;
use App\Enums\UnitStatus;
use App\Models\Community;
use App\Models\Unit;
use App\Models\UnitStatusHistory;
use App\Exports\AgeAnalysisExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * WeConnectU-style Customer Age Analysis (per community).
 *
 * Produces one row per customer (unit): the aged-arrears breakdown across the
 * buckets 120+ / 90 / 60 / 30 / Current, the net Balance, and the customer's
 * collection status / debit-order flag / notes count — exactly like the
 * WeConnectU "Customer Age Analysis" table. A totals row sums every column.
 *
 * Ageing is computed "as at" an Ageing Date (defaults to today): only invoices
 * raised on/before that date and payments received on/before that date are
 * considered, so a past period reflects the balances as they were then. Each
 * invoice is aged by its due_date relative to the Ageing Date:
 *   current  → not yet due (or due on the ageing date)
 *   30_days  → 1–30 days overdue
 *   60_days  → 31–60 days overdue
 *   90_days  → 61–90 days overdue
 *   120_plus → 90+ days overdue
 * Unallocated credits are netted oldest-bucket-first.
 */
class AgeAnalysisService extends BaseService
{
    /** Bucket keys, oldest → newest (credits net oldest-first). */
    private const BUCKETS = ['120_plus', '90_days', '60_days', '30_days', 'current'];

    /**
     * Build the age-analysis table for a community.
     *
     * Filters: ageing_date, ledger_id, exclude_debit_arrear, hide_zero,
     * hide_negative, filter_type, debt_status, customer_group_id, debit_order,
     * _search.
     *
     * @param Community $community
     * @param array $data
     * @return array{rows: array, totals: array, ageing_date: string}
     */
    public function getAgeAnalysis(Community $community, array $data): array
    {
        $ageingDate = $this->resolveAgeingDate($data);
        $rows       = $this->buildRows($community, $data, $ageingDate);

        // ── Row-level filters (WeConnectU toolbar) ───────────────────────
        $rows = $this->applyRowFilters($rows, $data);

        // ── Totals ───────────────────────────────────────────────────────
        $totals = [
            '120_plus'       => 0.0,
            '90_days'        => 0.0,
            '60_days'        => 0.0,
            '30_days'        => 0.0,
            'current'        => 0.0,
            'balance'        => 0.0,
            'customer_count' => count($rows),
        ];
        foreach ($rows as $r) {
            foreach (self::BUCKETS as $b) {
                $totals[$b] += $r[$b];
            }
            $totals['balance'] += $r['balance'];
        }
        foreach ($totals as $k => $v) {
            if ($k !== 'customer_count') {
                $totals[$k] = round($v, 2);
            }
        }

        return [
            'rows'        => $rows,
            'totals'      => $totals,
            'ageing_date' => $ageingDate->toDateString(),
        ];
    }

    /**
     * Assemble one row per unit (customer) with aged buckets + net balance,
     * reconstructed as at the ageing date.
     *
     * @param Community $community
     * @param array $data
     * @param Carbon $ageingDate
     * @return array
     */
    private function buildRows(Community $community, array $data, Carbon $ageingDate): array
    {
        $unitIds = Unit::where('community_id', $community->id)->pluck('id');

        if ($unitIds->isEmpty()) {
            return [];
        }

        // ── Customer subledger (GL) aged as at the ageing date ───────────
        // Every customer transaction — invoice AR debit, credit note, allocated
        // receipt, manual journal — is a CUSTOMER journal line. Debits age by
        // their due_date into the buckets; credits are a lump netted
        // oldest-bucket-first, keeping Age Analysis in lock-step with the
        // customer statement / ledger and UnitBalanceService.
        $bucketsByUnit = [];
        $creditsByUnit = [];

        $journalByUnit = (new JournalPostingService())->agedCustomerByUnit($unitIds->all(), $ageingDate->toDateString());
        foreach ($journalByUnit as $uid => $effect) {
            if ($effect['credit'] > 0) {
                $creditsByUnit[$uid] = ($creditsByUnit[$uid] ?? 0) + $effect['credit'];
            }
            foreach ($effect['debits'] as $debit) {
                $bucket                       = $this->bucketFor($debit['date'], $ageingDate);
                $bucketsByUnit[$uid]          ??= array_fill_keys(self::BUCKETS, 0.0);
                $bucketsByUnit[$uid][$bucket] += $debit['amount'];
            }
        }

        // ── Load the units that have arrears or credits ──────────────────
        $affectedUnitIds = array_values(array_unique(array_merge(
            array_keys($bucketsByUnit),
            array_keys($creditsByUnit),
        )));

        if (empty($affectedUnitIds)) {
            return [];
        }

        $units = Unit::whereIn('id', $affectedUnitIds)
            ->with(['owner.customerGroups', 'currentOccupant', 'community'])
            ->withCount('collectionNotes')
            ->get();

        // Who applied each customer's current collection status (acting user).
        $statusActors = UnitStatusHistory::latestActorsByUnit($affectedUnitIds);

        $customerGroupId = $data['customer_group_id'] ?? null;

        // ── Build a row per unit ─────────────────────────────────────────
        $rows = [];
        foreach ($units as $unit) {
            // Customer-group filter (owner belongs to the selected group).
            if (!empty($customerGroupId)) {
                $inGroup = $unit->owner
                    && $unit->owner->customerGroups->contains('id', $customerGroupId);
                if (!$inGroup) {
                    continue;
                }
            }

            $buckets = $bucketsByUnit[$unit->id] ?? array_fill_keys(self::BUCKETS, 0.0);
            $credit  = (float) ($creditsByUnit[$unit->id] ?? 0);

            // Net credits oldest-bucket-first.
            foreach (self::BUCKETS as $b) {
                if ($credit <= 0) {
                    break;
                }
                $reduce       = min($credit, $buckets[$b]);
                $buckets[$b] -= $reduce;
                $credit      -= $reduce;
            }

            $arrears = array_sum($buckets);
            $balance = round($arrears - $credit, 2); // leftover credit → negative balance

            if (abs($balance) < 0.005 && $arrears < 0.005) {
                continue; // fully settled, nothing to show
            }

            $person = $unit->owner ?: $unit->currentOccupant;
            $status = $unit->collection_status instanceof CollectionStatus
                ? $unit->collection_status
                : CollectionStatus::from($unit->collection_status ?? 'none');

            $isSold = $unit->status === UnitStatus::VACATED
                || ($unit->status instanceof UnitStatus ? false : ($unit->status === 'vacated'));

            $rows[] = [
                'unit_id'                 => $unit->id,
                'unit_number'             => $unit->unit_number,
                'unit_no'                 => $isSold ? '_' : $this->unitSortKey($unit->unit_number),
                'community_id'            => $unit->community_id,
                'community_name'          => $unit->community?->name,
                'customer_code'           => $unit->customer_code,
                'customer_name'           => $person?->full_name ?? '—',
                'customer_email'          => $person?->email,
                'person_id'               => $person?->id,
                'person_role'             => $unit->owner ? 'owner' : ($unit->currentOccupant ? 'occupant' : null),
                'collection_status'       => $status->value,
                'collection_status_label' => $status->label(),
                'status_changed_by'       => $statusActors[$unit->id][$status->value] ?? null,
                'debit_order'             => (bool) $unit->debit_order,
                'transfer_active'         => (bool) $unit->transfer_active,
                'is_sold'                 => $isSold,
                'notes_count'             => (int) ($unit->collection_notes_count ?? 0),
                '120_plus'                => round($buckets['120_plus'], 2),
                '90_days'                 => round($buckets['90_days'], 2),
                '60_days'                 => round($buckets['60_days'], 2),
                '30_days'                 => round($buckets['30_days'], 2),
                'current'                 => round($buckets['current'], 2),
                'balance'                 => $balance,
            ];
        }

        // Active units first (natural unit-number order); sold units to the bottom.
        usort($rows, function ($a, $b) {
            if ($a['is_sold'] !== $b['is_sold']) {
                return $a['is_sold'] <=> $b['is_sold'];
            }
            return $this->unitSortKey($a['unit_number']) <=> $this->unitSortKey($b['unit_number'])
                ?: strcmp((string) $a['unit_number'], (string) $b['unit_number']);
        });

        return $rows;
    }

    /**
     * Apply the WeConnectU toolbar filters to the assembled rows.
     *
     * @param array $rows
     * @param array $data
     * @return array
     */
    private function applyRowFilters(array $rows, array $data): array
    {
        // Filter Type: No Status / Handed Over / Payment Arrangement.
        if (!empty($data['filter_type']) && $data['filter_type'] !== 'all') {
            $type = $data['filter_type'] === 'no_status' ? 'none' : $data['filter_type'];
            $rows = array_filter($rows, fn ($r) => $r['collection_status'] === $type);
        }

        // Filter Debt Status: exact collection status.
        if (!empty($data['debt_status']) && $data['debt_status'] !== 'all') {
            $rows = array_filter($rows, fn ($r) => $r['collection_status'] === $data['debt_status']);
        }

        // Debit Order Customers.
        if (!empty($data['debit_order']) && filter_var($data['debit_order'], FILTER_VALIDATE_BOOLEAN)) {
            $rows = array_filter($rows, fn ($r) => $r['debit_order']);
        }

        // Hide Zero Values.
        if (!empty($data['hide_zero']) && filter_var($data['hide_zero'], FILTER_VALIDATE_BOOLEAN)) {
            $rows = array_filter($rows, fn ($r) => abs($r['balance']) >= 0.005);
        }

        // Hide Negative Values.
        if (!empty($data['hide_negative']) && filter_var($data['hide_negative'], FILTER_VALIDATE_BOOLEAN)) {
            $rows = array_filter($rows, fn ($r) => $r['balance'] >= -0.005);
        }

        // Free-text search (name / unit number / customer code).
        if (!empty($data['_search'])) {
            $term = mb_strtolower(trim($data['_search']));
            $rows = array_filter($rows, function ($r) use ($term) {
                return str_contains(mb_strtolower($r['customer_name'] ?? ''), $term)
                    || str_contains(mb_strtolower((string) $r['unit_number'] ?? ''), $term)
                    || str_contains(mb_strtolower($r['customer_code'] ?? ''), $term);
            });
        }

        return array_values($rows);
    }

    /**
     * Export the age analysis as a WeConnectU-faithful Excel workbook.
     *
     * @param Community $community
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportAgeAnalysis(Community $community, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $result   = $this->getAgeAnalysis($community, $data);
        $filename = 'customer age analysis-' . mb_strtolower($community->name) . '-' . $result['ageing_date'] . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new AgeAnalysisExport($community->name, $result['ageing_date'], $result['rows'], $result['totals']),
            $filename
        );
    }

    /**
     * Resolve the ageing "as at" date from the request (defaults to today).
     *
     * @param array $data
     * @return Carbon
     */
    private function resolveAgeingDate(array $data): Carbon
    {
        if (!empty($data['ageing_date'])) {
            return Carbon::parse($data['ageing_date'])->startOfDay();
        }

        return Carbon::today();
    }

    /**
     * Resolve the ageing bucket for an invoice due date relative to the ageing date.
     *
     * @param mixed $dueDate
     * @param Carbon $ageingDate
     * @return string
     */
    private function bucketFor($dueDate, Carbon $ageingDate): string
    {
        $due      = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate);
        $daysLate = $ageingDate->diffInDays($due, false); // negative = overdue

        return match (true) {
            $daysLate >= 0   => 'current',
            $daysLate >= -30 => '30_days',
            $daysLate >= -60 => '60_days',
            $daysLate >= -90 => '90_days',
            default          => '120_plus',
        };
    }

    /**
     * Numeric sort key from a unit number (e.g. "U12" → 12, "MML-A09" → 9).
     *
     * @param string|null $unitNumber
     * @return int
     */
    private function unitSortKey(?string $unitNumber): int
    {
        if (!$unitNumber) {
            return PHP_INT_MAX;
        }
        preg_match('/(\d+)(?!.*\d)/', $unitNumber, $m); // last run of digits
        return isset($m[1]) ? (int) $m[1] : PHP_INT_MAX;
    }
}
