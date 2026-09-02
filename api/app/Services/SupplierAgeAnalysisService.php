<?php

namespace App\Services;

use App\Exports\SupplierAgeAnalysisExport;
use App\Models\Community;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * WeConnectU-style Supplier Age Analysis (per community).
 *
 * Produces one row per supplier — the aged breakdown across the buckets
 * 120+ / 90 / 60 / 30 / Current and the net Balance — exactly like WeConnectU's
 * "Supplier Age Analysis" table, with a totals row summing every column. Each
 * supplier drills down into its Detailed-Ledger (Date · Source · Description ·
 * Remarks · Debit · Credit · Cumulative) via {@see supplierLedger()}.
 *
 * Ageing is "as at" an Ageing Date (defaults to today): only transactions dated
 * on/before it count, and the ledger is netted oldest-first with the remainder
 * aged by its transaction date (see {@see SupplierLedgerService}). A credit-heavy
 * supplier (money we owe) shows a NEGATIVE balance, matching WeConnectU.
 */
class SupplierAgeAnalysisService extends BaseService
{
    /**
     * @param SupplierLedgerService $ledger
     */
    public function __construct(private SupplierLedgerService $ledger)
    {
        parent::__construct();
    }

    /**
     * Build the supplier age-analysis table for a community.
     *
     * Filters: ageing_date, hide_zero, hide_negative, _search.
     *
     * @param Community $community
     * @param array $data
     * @return array{rows: array, totals: array, ageing_date: string}
     */
    public function getAgeAnalysis(Community $community, array $data): array
    {
        $ageingDate = $this->resolveAgeingDate($data);
        $rows       = $this->applyRowFilters($this->buildRows($community, $ageingDate), $data);

        $totals = [
            '120_plus'       => 0.0,
            '90_days'        => 0.0,
            '60_days'        => 0.0,
            '30_days'        => 0.0,
            'current'        => 0.0,
            'balance'        => 0.0,
            'supplier_count' => count($rows),
        ];
        foreach ($rows as $r) {
            foreach (SupplierLedgerService::BUCKETS as $b) {
                $totals[$b] += $r[$b];
            }
            $totals['balance'] += $r['balance'];
        }
        foreach ($totals as $k => $v) {
            if ($k !== 'supplier_count') {
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
     * The Detailed-Ledger drill-down for a single supplier, as at the ageing date.
     *
     * @param Community $community
     * @param Supplier $supplier
     * @param array $data
     * @return array{ledger: array, ageing_date: string}
     */
    public function supplierLedger(Community $community, Supplier $supplier, array $data): array
    {
        $ageingDate = $this->resolveAgeingDate($data);

        return [
            'ledger'      => $this->ledger->ledgerFor($supplier, $ageingDate->toDateString(), $community->id),
            'ageing_date' => $ageingDate->toDateString(),
        ];
    }

    /**
     * Export the age analysis as an Excel workbook (WeConnectU-faithful layout).
     *
     * @param Community $community
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportAgeAnalysis(Community $community, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $result   = $this->getAgeAnalysis($community, $data);
        $filename = 'supplier age analysis-' . mb_strtolower($community->name) . '-' . $result['ageing_date'] . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new SupplierAgeAnalysisExport($community->name, $result['ageing_date'], $result['rows'], $result['totals']),
            $filename
        );
    }

    /**
     * Assemble one row per supplier with aged buckets + net balance.
     *
     * @param Community $community
     * @param Carbon $ageingDate
     * @return array
     */
    private function buildRows(Community $community, Carbon $ageingDate): array
    {
        $organizationId = Auth::user()->organization_id;

        $supplierIds = Supplier::where('organization_id', $organizationId)
            ->forCommunity($community->id)
            ->pluck('id')
            ->all();

        if (empty($supplierIds)) {
            return [];
        }

        $aged = $this->ledger->agedBySupplier($supplierIds, $ageingDate->toDateString(), $community->id);

        if (empty($aged)) {
            return [];
        }

        $suppliers = Supplier::whereIn('id', array_keys($aged))->get()->keyBy('id');

        $rows = [];
        foreach ($aged as $supplierId => $effect) {
            $supplier = $suppliers->get($supplierId);
            if (! $supplier) {
                continue;
            }

            $buckets = $effect['buckets'];
            $rows[]  = [
                'supplier_id'   => $supplier->id,
                'supplier_code' => $supplier->supplier_code,
                'supplier_name' => $supplier->name,
                'label'         => trim(($supplier->supplier_code ? $supplier->supplier_code . ': ' : '') . $supplier->name),
                '120_plus'      => $buckets['120_plus'],
                '90_days'       => $buckets['90_days'],
                '60_days'       => $buckets['60_days'],
                '30_days'       => $buckets['30_days'],
                'current'       => $buckets['current'],
                'balance'       => $effect['balance'],
            ];
        }

        // WeConnectU sorts by Supplier (code) ascending.
        usort($rows, fn ($a, $b) => strcmp((string) $a['supplier_code'], (string) $b['supplier_code']));

        return $rows;
    }

    /**
     * Apply the WeConnectU toolbar filters (hide zero / hide negative / search).
     *
     * @param array $rows
     * @param array $data
     * @return array
     */
    private function applyRowFilters(array $rows, array $data): array
    {
        if (! empty($data['hide_zero']) && filter_var($data['hide_zero'], FILTER_VALIDATE_BOOLEAN)) {
            $rows = array_filter($rows, fn ($r) => abs($r['balance']) >= 0.005);
        }

        if (! empty($data['hide_negative']) && filter_var($data['hide_negative'], FILTER_VALIDATE_BOOLEAN)) {
            $rows = array_filter($rows, fn ($r) => $r['balance'] >= -0.005);
        }

        if (! empty($data['_search'])) {
            $term = mb_strtolower(trim($data['_search']));
            $rows = array_filter($rows, function ($r) use ($term) {
                return str_contains(mb_strtolower($r['supplier_name'] ?? ''), $term)
                    || str_contains(mb_strtolower((string) $r['supplier_code'] ?? ''), $term);
            });
        }

        return array_values($rows);
    }

    /**
     * Resolve the ageing "as at" date from the request (defaults to today).
     *
     * @param array $data
     * @return Carbon
     */
    private function resolveAgeingDate(array $data): Carbon
    {
        if (! empty($data['ageing_date'])) {
            return Carbon::parse($data['ageing_date'])->startOfDay();
        }

        return Carbon::today();
    }
}
