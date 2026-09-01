<?php

namespace App\Services;

use App\Helpers\CountryHelper;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\ComplianceChecklistItem;
use App\Models\Invoice;
use App\Models\Unit;
use App\Enums\CashbookEntryType;
use App\Enums\ComplianceItemStatus;
use App\Enums\InvoiceStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Return all summary data needed to render the dashboard.
     *
     * @return array
     */
    public function getDashboardSummary(?string $country = null, ?int $complianceYear = null): array
    {
        $user     = Auth::user();
        $organizationId = $user->organization_id;

        // ------- Summary cards — single round trip via subqueries -------

        if ($country) {
            // Country-filtered variant: JOIN communities to scope by country
            $summary = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM communities WHERE organization_id = :tid1 AND country = :c1) AS total_communities,
                    (SELECT COUNT(*) FROM units JOIN communities ON communities.id = units.community_id WHERE units.organization_id = :tid2 AND communities.country = :c2) AS total_units,
                    (SELECT COUNT(*) FROM units JOIN communities ON communities.id = units.community_id WHERE units.organization_id = :tid3 AND communities.country = :c3 AND units.occupancy_type IN ('owner_occupied','occupant_occupied')) AS occupied_units,
                    (SELECT COUNT(*) FROM units JOIN communities ON communities.id = units.community_id WHERE units.organization_id = :tid4 AND communities.country = :c4 AND units.occupancy_type = 'vacant') AS vacant_units,
                    (SELECT COALESCE(SUM(invoices.amount),0) FROM invoices JOIN units ON units.id = invoices.unit_id JOIN communities ON communities.id = units.community_id WHERE invoices.organization_id = :tid5 AND communities.country = :c5 AND invoices.status IN (:s1,:s2,:s3)) AS total_outstanding,
                    (SELECT COUNT(*) FROM invoices JOIN units ON units.id = invoices.unit_id JOIN communities ON communities.id = units.community_id WHERE invoices.organization_id = :tid5b AND communities.country = :c5b AND invoices.status IN (:s1b,:s2b,:s3b)) AS unpaid_invoices_count,
                    (SELECT COALESCE(SUM(cashbook_entries.amount),0) FROM cashbook_entries JOIN communities ON communities.id = cashbook_entries.community_id WHERE cashbook_entries.organization_id = :tid6 AND communities.country = :c6 AND cashbook_entries.type = :ctype AND cashbook_entries.date >= :mstart AND cashbook_entries.date < :mend) AS collected_this_month,
                    (SELECT COUNT(*) FROM cashbook_entries JOIN communities ON communities.id = cashbook_entries.community_id WHERE cashbook_entries.organization_id = :tid6b AND communities.country = :c6b AND cashbook_entries.type = :ctype2 AND cashbook_entries.date >= :mstart2 AND cashbook_entries.date < :mend2) AS payments_this_month_count,
                    (SELECT COUNT(*) FROM cashbook_entries JOIN communities ON communities.id = cashbook_entries.community_id WHERE cashbook_entries.organization_id = :tid7 AND communities.country = :c7) AS total_cashbook_entries
            ", [
                'tid1'  => $organizationId, 'c1'  => $country,
                'tid2'  => $organizationId, 'c2'  => $country,
                'tid3'  => $organizationId, 'c3'  => $country,
                'tid4'  => $organizationId, 'c4'  => $country,
                'tid5'  => $organizationId, 'c5'  => $country,
                's1'    => InvoiceStatus::UNPAID->value,
                's2'    => InvoiceStatus::OVERDUE->value,
                's3'    => InvoiceStatus::PARTIALLY_PAID->value,
                'tid5b' => $organizationId, 'c5b' => $country,
                's1b'   => InvoiceStatus::UNPAID->value,
                's2b'   => InvoiceStatus::OVERDUE->value,
                's3b'   => InvoiceStatus::PARTIALLY_PAID->value,
                'tid6'   => $organizationId, 'c6'  => $country,
                'ctype'  => CashbookEntryType::CREDIT->value,
                'mstart' => now()->startOfMonth()->toDateString(),
                'mend'   => now()->addMonth()->startOfMonth()->toDateString(),
                'tid6b'  => $organizationId, 'c6b' => $country,
                'ctype2' => CashbookEntryType::CREDIT->value,
                'mstart2' => now()->startOfMonth()->toDateString(),
                'mend2'   => now()->addMonth()->startOfMonth()->toDateString(),
                'tid7'  => $organizationId, 'c7'  => $country,
            ]);
        } else {
            // Unfiltered variant: original query
            $summary = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM communities  WHERE organization_id = :tid1) AS total_communities,
                    (SELECT COUNT(*) FROM units    WHERE organization_id = :tid2) AS total_units,
                    (SELECT COUNT(*) FROM units    WHERE organization_id = :tid3 AND occupancy_type IN ('owner_occupied','occupant_occupied')) AS occupied_units,
                    (SELECT COUNT(*) FROM units    WHERE organization_id = :tid4 AND occupancy_type = 'vacant') AS vacant_units,
                    (SELECT COALESCE(SUM(amount),0) FROM invoices WHERE organization_id = :tid5 AND status IN (:s1,:s2,:s3)) AS total_outstanding,
                    (SELECT COUNT(*) FROM invoices WHERE organization_id = :tid5b AND status IN (:s1b,:s2b,:s3b)) AS unpaid_invoices_count,
                    (SELECT COALESCE(SUM(amount),0) FROM cashbook_entries WHERE organization_id = :tid6 AND type = :ctype AND date >= :mstart AND date < :mend) AS collected_this_month,
                    (SELECT COUNT(*) FROM cashbook_entries WHERE organization_id = :tid6b AND type = :ctype2 AND date >= :mstart2 AND date < :mend2) AS payments_this_month_count,
                    (SELECT COUNT(*) FROM cashbook_entries WHERE organization_id = :tid7) AS total_cashbook_entries
            ", [
                'tid1'  => $organizationId,
                'tid2'  => $organizationId,
                'tid3'  => $organizationId,
                'tid4'  => $organizationId,
                'tid5'  => $organizationId,
                's1'    => InvoiceStatus::UNPAID->value,
                's2'    => InvoiceStatus::OVERDUE->value,
                's3'    => InvoiceStatus::PARTIALLY_PAID->value,
                'tid5b' => $organizationId,
                's1b'   => InvoiceStatus::UNPAID->value,
                's2b'   => InvoiceStatus::OVERDUE->value,
                's3b'   => InvoiceStatus::PARTIALLY_PAID->value,
                'tid6'   => $organizationId,
                'ctype'  => CashbookEntryType::CREDIT->value,
                'mstart' => now()->startOfMonth()->toDateString(),
                'mend'   => now()->addMonth()->startOfMonth()->toDateString(),
                'tid6b'  => $organizationId,
                'ctype2' => CashbookEntryType::CREDIT->value,
                'mstart2' => now()->startOfMonth()->toDateString(),
                'mend2'   => now()->addMonth()->startOfMonth()->toDateString(),
                'tid7'  => $organizationId,
            ]);
        }

        $totalUnits    = (int) $summary->total_units;
        $occupiedUnits = (int) $summary->occupied_units;
        $occupancyRate = $totalUnits > 0
            ? round(($occupiedUnits / $totalUnits) * 100, 1)
            : 0;

        // ------- Recent invoices — single query with joins -------

        $recentInvoices = Invoice::where('invoices.organization_id', $organizationId)
            ->when($country, fn($q) => $q->whereHas('unit.community', fn($eq) => $eq->where('country', $country)))
            ->with(['unit', 'ledger', 'billedToOwner', 'billedToUnitOccupant'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn($invoice) => [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status'         => $invoice->status instanceof InvoiceStatus
                    ? $invoice->status->value
                    : $invoice->status,
                'amount'         => $invoice->amount,
                'ledger'    => $invoice->ledger?->name,
                'unit_number'    => $invoice->unit?->unit_number,
                'billing_period' => $invoice->billing_period?->format('Y-m'),
                'due_date'       => $invoice->due_date?->format('Y-m-d'),
                'billed_to_name' => match (true) {
                    $invoice->billed_to_type === \App\Enums\BilledToType::OWNER  => $invoice->billedToOwner?->full_name,
                    $invoice->billed_to_type === \App\Enums\BilledToType::OCCUPANT => $invoice->billedToUnitOccupant?->full_name,
                    default => null,
                },
            ]);

        // ------- Communities overview — single query with conditional counts -------

        $communitiesOverview = Community::where('organization_id', $organizationId)
            ->when($country, fn($q) => $q->where('country', $country))
            ->withCount([
                'units',
                'units as owner_occupied_count' => fn($q) => $q->where('occupancy_type', 'owner_occupied'),
                'units as occupant_occupied_count' => fn($q) => $q->where('occupancy_type', 'occupant_occupied'),
                'units as vacant_count'          => fn($q) => $q->where('occupancy_type', 'vacant'),
            ])
            ->withSum('units', 'rent_amount')
            ->latest()
            ->get()
            ->map(function ($community) {
                $basis       = $community->entity_type instanceof \App\Enums\CommunityEntityType
                    ? $community->entity_type->billingBasis()
                    : 'levy';
                $levyRevenue = (float) ($community->admin_fund_amount ?? 0) + (float) ($community->reserve_fund_amount ?? 0);
                $rentRevenue = (float) ($community->units_sum_rent_amount ?? 0);
                $monthlyRevenue = match ($basis) {
                    'rent'  => $rentRevenue,
                    'mixed' => $levyRevenue + $rentRevenue,
                    default => $levyRevenue,
                };

                return [
                    'id'                    => $community->id,
                    'name'                  => $community->name,
                    'code'                  => $community->code,
                    'address'               => $community->address,
                    'entity_type'           => $community->entity_type instanceof \App\Enums\CommunityEntityType
                        ? $community->entity_type->value
                        : $community->entity_type,
                    'country'               => $community->country,
                    'units_count'           => $community->units_count,
                    'owner_occupied_count'  => $community->owner_occupied_count,
                    'occupant_occupied_count' => $community->occupant_occupied_count,
                    'occupied_units_count'  => $community->owner_occupied_count + $community->occupant_occupied_count,
                    'vacant_count'          => $community->vacant_count,
                    'financial_year_end_month' => $community->financial_year_end_month,
                    'monthly_revenue'       => $monthlyRevenue,
                ];
            });

        return [
            'summary' => [
                'total_communities'        => (int) $summary->total_communities,
                'total_units'          => $totalUnits,
                'total_outstanding'    => (float) $summary->total_outstanding,
                'unpaid_invoices_count' => (int) $summary->unpaid_invoices_count,
                'collected_this_month' => (float) $summary->collected_this_month,
                'payments_this_month_count' => (int) $summary->payments_this_month_count,
                'occupied_units'       => $occupiedUnits,
                'vacant_units'         => (int) $summary->vacant_units,
                'occupancy_rate'       => $occupancyRate,
                'total_cashbook_entries' => (int) $summary->total_cashbook_entries,
            ],
            'recent_invoices'  => $recentInvoices,
            'communities_overview' => $communitiesOverview,
            'debt_trend'       => $this->debtTrend($organizationId, $country, (float) $summary->total_outstanding),
            'compliance'       => $this->complianceBreakdown($organizationId, $country, $complianceYear),
            'tasks'            => $this->tasksBreakdown($organizationId, $country, $complianceYear),
            'currency'         => $country ? CountryHelper::get($country) : null,
        ];
    }

    /**
     * Build the rolling debt (arrears) trend for the Debt panel area chart.
     *
     * The currently-outstanding invoices are bucketed by the month they were
     * billed, then made cumulative across a 6-month window. Debt billed before
     * the window is rolled into the opening baseline, so the final point of the
     * series equals the current total outstanding.
     *
     * @return array{total: float, percent_change: float, series: array<int, array{label: string, value: float}>}
     */
    private function debtTrend(string $organizationId, ?string $country, float $totalOutstanding): array
    {
        $invoices = Invoice::where('organization_id', $organizationId)
            ->whereIn('status', [
                InvoiceStatus::UNPAID->value,
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PARTIALLY_PAID->value,
            ])
            ->when($country, fn ($q) => $q->whereHas('unit.community', fn ($c) => $c->where('country', $country)))
            ->get(['billing_period', 'created_at', 'amount']);

        // Sum outstanding amounts by the calendar month they were billed.
        $byMonth = [];
        foreach ($invoices as $invoice) {
            $date = $invoice->billing_period ?? $invoice->created_at;
            $key  = $date ? $date->format('Y-m') : now()->format('Y-m');
            $byMonth[$key] = ($byMonth[$key] ?? 0) + (float) $invoice->amount;
        }

        // The 6 months ending with the current month.
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = now()->startOfMonth()->subMonths($i);
        }
        $windowStartKey = $months[0]->format('Y-m');

        // Everything billed before the window forms the opening baseline.
        $baseline = 0.0;
        foreach ($byMonth as $key => $amount) {
            if ($key < $windowStartKey) {
                $baseline += $amount;
            }
        }

        $series  = [];
        $running = $baseline;
        foreach ($months as $month) {
            $running += $byMonth[$month->format('Y-m')] ?? 0;
            $series[] = [
                'label' => $month->format('M'),
                'value' => round($running, 2),
            ];
        }

        $last = $series[count($series) - 1]['value'] ?? 0;
        $prev = $series[count($series) - 2]['value'] ?? 0;
        $percentChange = $prev > 0
            ? round((($last - $prev) / $prev) * 100, 1)
            : ($last > 0 ? 100.0 : 0.0);

        return [
            'total'          => round($totalOutstanding, 2),
            'percent_change' => $percentChange,
            'series'         => $series,
        ];
    }

    /**
     * Aggregate compliance items into the WeConnectU-style status split used by
     * the Compliance doughnut (Planned / Compliant / Unplanned / Non Compliant).
     *
     * When a year is supplied, only items due within that calendar year are
     * counted — this backs the year pills beneath the Compliance doughnut.
     *
     * @return array{compliant: int, non_compliant: int, planned: int, unplanned: int, total: int, percent_compliant: float}
     */
    private function complianceBreakdown(string $organizationId, ?string $country, ?int $year = null): array
    {
        $counts = ComplianceChecklistItem::query()
            ->where('compliance_checklist_items.organization_id', $organizationId)
            ->when($country, fn ($q) => $q->whereHas('checklist.community', fn ($c) => $c->where('country', $country)))
            ->when($year, fn ($q) => $q->whereYear('due_date', $year))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $compliant    = (int) ($counts[ComplianceItemStatus::COMPLETED->value] ?? 0);
        $nonCompliant = (int) ($counts[ComplianceItemStatus::OVERDUE->value] ?? 0);
        $planned      = (int) ($counts[ComplianceItemStatus::PENDING->value] ?? 0)
                      + (int) ($counts[ComplianceItemStatus::IN_PROGRESS->value] ?? 0);
        $unplanned    = (int) ($counts[ComplianceItemStatus::WAIVED->value] ?? 0);

        // Waived items are excluded from the compliance percentage denominator.
        $applicable = $compliant + $nonCompliant + $planned;
        $percentCompliant = $applicable > 0 ? round(($compliant / $applicable) * 100) : 0;

        return [
            'compliant'         => $compliant,
            'non_compliant'     => $nonCompliant,
            'planned'           => $planned,
            'unplanned'         => $unplanned,
            'total'             => $compliant + $nonCompliant + $planned + $unplanned,
            'percent_compliant' => (float) $percentCompliant,
        ];
    }

    /**
     * Build the WeConnectU-style Tasks matrix for the dashboard — a per-month
     * breakdown of Total / Active-Overdue / Complete counts plus the completion
     * strike rate.
     *
     * There is no Tasks module in the system yet, so every bucket is currently
     * zeroed. The shape mirrors the finished feature so the frontend table can be
     * wired now and light up automatically once real task data lands.
     *
     * Columns run from January to the current month for the active year, and the
     * full twelve months for any other year (matching the compliance year pills).
     *
     * @return array{months: array<int, string>, total: array<int, int>, active_overdue: array<int, int>, complete: array<int, int>, strike_rate: array<int, int>}
     */
    private function tasksBreakdown(string $organizationId, ?string $country, ?int $year = null): array
    {
        $year          = $year ?? (int) now()->year;
        $isCurrentYear = $year === (int) now()->year;
        $monthCount    = $isCurrentYear ? (int) now()->month : 12;

        $months        = [];
        $total         = [];
        $activeOverdue = [];
        $complete      = [];
        $strikeRate    = [];

        for ($month = 1; $month <= $monthCount; $month++) {
            $months[]        = \Illuminate\Support\Carbon::create($year, $month, 1)->format('M');
            $total[]         = 0;
            $activeOverdue[] = 0;
            $complete[]      = 0;
            $strikeRate[]    = 0;
        }

        return [
            'months'         => $months,
            'total'          => $total,
            'active_overdue' => $activeOverdue,
            'complete'       => $complete,
            'strike_rate'    => $strikeRate,
        ];
    }
}
