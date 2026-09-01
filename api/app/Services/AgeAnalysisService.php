<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Unit;
use App\Models\CashbookEntry;
use App\Enums\CashbookEntryType;
use App\Enums\CollectionStatus;
use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * WeConnectU-style Age Analysis.
 *
 * Produces one row per customer (unit): the aged-arrears breakdown across the
 * buckets 120+ / 90 / 60 / 30 / Current, the net Balance, and the customer's
 * collection status / debit-order flag / notes count — exactly like the
 * WeConnectU "Age Analysis" table. A totals row sums every column.
 *
 * Ageing is by invoice due_date:
 *   current  → not yet due (or due today)
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

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Build the age-analysis table for the authenticated user's organization.
     *
     * Filters: community_id, country, ledger_id, _search, debt_status, debit_order.
     *
     * @param array $data
     * @return array{rows: array, totals: array}
     */
    public function getAgeAnalysis(array $data): array
    {
        $rows = $this->buildRows($data);

        // ── Filters that apply to the assembled rows ─────────────────────
        if (!empty($data['debt_status']) && $data['debt_status'] !== 'all') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['collection_status'] === $data['debt_status']));
        }

        if (!empty($data['debit_order']) && filter_var($data['debit_order'], FILTER_VALIDATE_BOOLEAN)) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['debit_order']));
        }

        if (!empty($data['_search'])) {
            $term = mb_strtolower(trim($data['_search']));
            $rows = array_values(array_filter($rows, function ($r) use ($term) {
                return str_contains(mb_strtolower($r['customer_name'] ?? ''), $term)
                    || str_contains(mb_strtolower($r['unit_number'] ?? ''), $term)
                    || str_contains(mb_strtolower($r['customer_code'] ?? ''), $term);
            }));
        }

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

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Assemble one row per unit (customer) with aged buckets + net balance.
     *
     * @param array $data
     * @return array
     */
    private function buildRows(array $data): array
    {
        $organizationId = Auth::user()->organization_id;
        $today          = Carbon::today();

        // ── Outstanding invoices → per-unit bucket sums ──────────────────
        $invoiceQuery = Invoice::where('organization_id', $organizationId)
            ->whereIn('status', [
                InvoiceStatus::UNPAID->value,
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PARTIALLY_PAID->value,
            ]);

        $this->applyScopeFilters($invoiceQuery, $data, 'unit_id');
        if (!empty($data['ledger_id'])) {
            $invoiceQuery->where('ledger_id', $data['ledger_id']);
        }

        $bucketsByUnit = [];
        foreach ($invoiceQuery->get(['id', 'unit_id', 'due_date', 'amount', 'status']) as $invoice) {
            $outstanding = (float) $invoice->outstanding;
            if ($outstanding <= 0) {
                continue;
            }

            $bucket = $this->bucketFor($invoice->due_date, $today);
            $unitId = $invoice->unit_id;

            if (!isset($bucketsByUnit[$unitId])) {
                $bucketsByUnit[$unitId] = array_fill_keys(self::BUCKETS, 0.0);
            }
            $bucketsByUnit[$unitId][$bucket] += $outstanding;
        }

        // ── Unallocated credits per unit ─────────────────────────────────
        $creditQuery = CashbookEntry::where('organization_id', $organizationId)
            ->whereNull('invoice_id')
            ->where('type', CashbookEntryType::CREDIT->value);
        $this->applyScopeFilters($creditQuery, $data, 'unit_id');

        $creditsByUnit = $creditQuery
            ->selectRaw('unit_id, SUM(amount) as total')
            ->groupBy('unit_id')
            ->pluck('total', 'unit_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // ── Load the units that have arrears or credits ──────────────────
        $unitIds = array_values(array_unique(array_merge(
            array_keys($bucketsByUnit),
            array_keys($creditsByUnit),
        )));

        if (empty($unitIds)) {
            return [];
        }

        $units = Unit::whereIn('id', $unitIds)
            ->with(['owner', 'currentOccupant', 'community'])
            ->withCount('collectionNotes')
            ->get();

        // ── Build a row per unit ─────────────────────────────────────────
        $rows = [];
        foreach ($units as $unit) {
            $buckets = $bucketsByUnit[$unit->id] ?? array_fill_keys(self::BUCKETS, 0.0);
            $credit  = (float) ($creditsByUnit[$unit->id] ?? 0);

            // Net credits oldest-bucket-first.
            foreach (self::BUCKETS as $b) {
                if ($credit <= 0) {
                    break;
                }
                $reduce = min($credit, $buckets[$b]);
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

            $rows[] = [
                'unit_id'          => $unit->id,
                'unit_number'      => $unit->unit_number,
                'community_id'     => $unit->community_id,
                'community_name'   => $unit->community?->name,
                'customer_code'    => $unit->customer_code,
                'customer_name'    => $person?->full_name ?? '—',
                'customer_email'   => $person?->email,
                'person_id'        => $person?->id,
                'person_role'      => $unit->owner ? 'owner' : ($unit->currentOccupant ? 'occupant' : null),
                'collection_status'       => $status->value,
                'collection_status_label' => $status->label(),
                'debit_order'      => (bool) $unit->debit_order,
                'is_sold'          => false,
                'notes_count'      => (int) ($unit->collection_notes_count ?? 0),
                '120_plus'         => round($buckets['120_plus'], 2),
                '90_days'          => round($buckets['90_days'], 2),
                '60_days'          => round($buckets['60_days'], 2),
                '30_days'          => round($buckets['30_days'], 2),
                'current'          => round($buckets['current'], 2),
                'balance'          => $balance,
            ];
        }

        // Sort by unit number ascending (natural), like WeConnectU.
        usort($rows, fn ($a, $b) => $this->unitSortKey($a['unit_number']) <=> $this->unitSortKey($b['unit_number'])
            ?: strcmp((string) $a['unit_number'], (string) $b['unit_number']));

        return $rows;
    }

    /**
     * Apply org-consistent community/country scope to an invoice or cashbook query.
     */
    private function applyScopeFilters($query, array $data, string $unitColumn): void
    {
        if (!empty($data['community_id'])) {
            $unitIds = Unit::where('community_id', $data['community_id'])->pluck('id');
            $query->whereIn($unitColumn, $unitIds);
        }

        if (!empty($data['country'])) {
            $unitIds = Unit::whereHas('community', fn ($c) => $c->where('country', $data['country']))->pluck('id');
            $query->whereIn($unitColumn, $unitIds);
        }
    }

    /**
     * Resolve the ageing bucket for an invoice due date.
     */
    private function bucketFor($dueDate, Carbon $today): string
    {
        $due      = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate);
        $daysLate = $today->diffInDays($due, false); // negative = overdue

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
     */
    private function unitSortKey(?string $unitNumber): int
    {
        if (!$unitNumber) {
            return PHP_INT_MAX;
        }
        preg_match('/(\d+)(?!.*\d)/', $unitNumber, $m); // last run of digits
        return isset($m[1]) ? (int) $m[1] : PHP_INT_MAX;
    }

    /**
     * Export the age analysis as CSV / Excel / PDF, matching the on-screen columns.
     *
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportAgeAnalysis(array $data): \Symfony\Component\HttpFoundation\Response
    {
        $result = $this->getAgeAnalysis($data);

        $headings = ['Unit No', 'Customer', 'Status', '120+ Days', '90 Days', '60 Days', '30 Days', 'Current', 'Balance'];

        $rows = [];
        foreach ($result['rows'] as $row) {
            $rows[] = [
                $row['unit_number'] ?? '—',
                trim(($row['customer_code'] ? $row['customer_code'] . ': ' : '') . ($row['customer_name'] ?? '—')),
                $row['collection_status_label'] ?: '—',
                number_format((float) $row['120_plus'], 2),
                number_format((float) $row['90_days'], 2),
                number_format((float) $row['60_days'], 2),
                number_format((float) $row['30_days'], 2),
                number_format((float) $row['current'], 2),
                number_format((float) $row['balance'], 2),
            ];
        }

        return $this->buildFileResponse(
            $rows,
            $headings,
            'age-analysis-' . now()->format('Y-m-d'),
            $data['_format'] ?? 'xlsx',
            'Age Analysis Export',
            [
                'Generated'  => now()->format('d M Y'),
                'Balance'    => number_format((float) ($result['totals']['balance'] ?? 0), 2),
                'Customers'  => $result['totals']['customer_count'] ?? 0,
            ]
        );
    }

    /**
     * Bulk "Send Notices": advance the collection status of the arrears customers
     * in scope to the next escalation step and log a collection note per unit.
     *
     * @param array $data
     * @return array
     */
    public function sendNotices(array $data): array
    {
        $result = $this->getAgeAnalysis($data);
        $userName = Auth::user()?->name ?? 'System';
        $userId   = Auth::user()?->id;

        $sent = 0;
        foreach ($result['rows'] as $row) {
            if ($row['balance'] <= 0) {
                continue; // only chase real arrears
            }

            $unit = Unit::find($row['unit_id']);
            if (!$unit) {
                continue;
            }

            $current = $unit->collection_status instanceof CollectionStatus
                ? $unit->collection_status
                : CollectionStatus::from($unit->collection_status ?? 'none');
            $next = $current->next();

            $unit->update(['collection_status' => $next->value]);

            \App\Models\UnitCollectionNote::create([
                'unit_id'         => $unit->id,
                'organization_id' => $unit->organization_id,
                'note'            => $next->label() . ' sent',
                'created_by_name' => $userName,
                'user_id'         => $userId,
            ]);

            $sent++;
        }

        return [
            'sent'    => $sent,
            'message' => "{$sent} notice(s) sent.",
        ];
    }
}
