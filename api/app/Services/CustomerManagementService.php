<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\CashbookEntry;
use App\Enums\InvoiceStatus;
use App\Http\Resources\UnitResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerManagementService extends BaseService
{
    /**
     * Return paginated units with overdue invoices across all communities,
     * along with summary stats for the arrears page.
     *
     * A unit is "in arrears" when it has at least one invoice with status = overdue.
     *
     * Supported query parameters:
     *   _search           → unit_number, owner name, or community name
     *   _sort             → unit_number:asc/desc | owner_name:asc/desc | community_name:asc/desc
     *                       | overdue_amount:asc/desc | oldest_overdue:asc/desc
     *   _date_range       → today | this_week | this_month | this_year | custom | all_time
     *   _date_range_start → Y-m-d
     *   _date_range_end   → Y-m-d
     *   community_id         → filter to a specific community
     *   community_type       → sectional_title | residential_rental | commercial_rental | mixed
     *   ledger       → filter by ledger id
     *   _per_page         → pagination size (default 15)
     *
     * @param array $data
     * @return array
     */
    public function showCustomers(array $data): array
    {
        $user = Auth::user();
        $organizationId = $user->organization_id;

        // ── Units with overdue invoices ─────────────────────────────────
        $query = Unit::where('units.organization_id', $organizationId)
            ->with(['owner', 'currentOccupant', 'community:id,name,entity_type,address'])
            ->whereHas('invoices', function ($q) use ($data) {
                $q->where('status', InvoiceStatus::OVERDUE);
                if (!empty($data['ledger'])) {
                    $q->where('ledger_id', $data['ledger']);
                }
            });

        // Add computed overdue columns for display and sorting
        $query->addSelect([
            'units.*',

            // Total overdue amount (sum of overdue invoice amounts minus allocated payments)
            'overdue_amount' => Invoice::selectRaw(
                "COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0)"
            )
                ->whereColumn('invoices.unit_id', 'units.id')
                ->where('invoices.status', InvoiceStatus::OVERDUE),

            // Count of overdue invoices
            'overdue_count' => Invoice::selectRaw('COUNT(*)')
                ->whereColumn('invoices.unit_id', 'units.id')
                ->where('invoices.status', InvoiceStatus::OVERDUE),

            // Oldest overdue invoice date
            'oldest_overdue_date' => Invoice::selectRaw('MIN(invoices.due_date)')
                ->whereColumn('invoices.unit_id', 'units.id')
                ->where('invoices.status', InvoiceStatus::OVERDUE),
        ]);

        // Filter by country (scoped portfolio)
        if (!empty($data['country'])) {
            $query->whereHas('community', fn($q) => $q->where('country', $data['country']));
        }

        // Filter by community
        if (!empty($data['community_id'])) {
            $query->where('units.community_id', $data['community_id']);
        }

        // Filter by community entity type
        if (!empty($data['community_type'])) {
            $query->whereHas('community', fn($q) => $q->where('entity_type', $data['community_type']));
        }

        // Search
        if (!empty($data['_search'])) {
            $search = $data['_search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('units.unit_number', $search)
                  ->orWhereHas('owner', fn($o) => $o->whereLike('full_name', $search))
                  ->orWhereHas('community', fn($e) => $e->whereLike('name', $search));
            });
        }

        // Default sort: highest overdue amount first
        if (!request()->has('_sort')) {
            $query->orderByDesc('overdue_amount');
        }

        $this->setQuery($query);
        $this->applyDateRangeFromRequest();
        $this->applySortOnQuery();

        $perPage   = max(1, (int) request()->input('_per_page', $this->defaultPerPage));
        $paginated = $this->query->paginate($perPage)->withQueryString();

        // ── Summary stats ───────────────────────────────────────────────
        $unitsInArrears = Unit::where('units.organization_id', $organizationId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        $totalUnits = Unit::where('organization_id', $organizationId)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        $totalOverdueAmount = Invoice::where('invoices.organization_id', $organizationId)
            ->where('invoices.status', InvoiceStatus::OVERDUE)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('unit.community', fn($eq) => $eq->where('country', $data['country'])))
            ->selectRaw(
                "COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0) as total"
            )
            ->value('total');

        $totalOverdueInvoices = Invoice::where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::OVERDUE)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('unit.community', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        // Count of distinct communities with overdue units (uncapped)
        $communitiesAffected = Unit::where('units.organization_id', $organizationId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->distinct('community_id')
            ->count('community_id');

        // Breakdown by community (top 5 by overdue amount)
        $byCommunity = Unit::where('units.organization_id', $organizationId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->join('communities', 'communities.id', '=', 'units.community_id')
            ->join('invoices', function ($join) {
                $join->on('invoices.unit_id', '=', 'units.id')
                     ->where('invoices.status', InvoiceStatus::OVERDUE);
            })
            ->selectRaw("communities.id, communities.name, communities.entity_type, COUNT(DISTINCT units.id) as units_count, COALESCE(SUM(invoices.amount), 0) as overdue_total")
            ->groupBy('communities.id', 'communities.name', 'communities.entity_type')
            ->orderByDesc('overdue_total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id'            => $row->id,
                'name'          => $row->name,
                'entity_type'   => $row->entity_type,
                'units_count'   => (int) $row->units_count,
                'overdue_total' => (float) $row->overdue_total,
            ])
            ->toArray();

        // Communities dropdown for filter
        $communitiesQuery = Community::where('organization_id', $organizationId);
        if (!empty($data['country'])) {
            $communitiesQuery->where('country', $data['country']);
        }
        $communities = $communitiesQuery
            ->select('id', 'name', 'entity_type')
            ->orderBy('name')
            ->get()
            ->map(fn ($e) => [
                'id'   => $e->id,
                'name' => $e->name,
                'entity_type' => $e->entity_type instanceof \BackedEnum ? $e->entity_type->value : $e->entity_type,
            ])
            ->toArray();

        // Ledgers for filter
        $ledgers = DB::table('ledgers')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($ct) => [
                'id'   => $ct->id,
                'name' => $ct->name,
            ])
            ->toArray();

        // ── Chart data ──────────────────────────────────────────────────

        // Arrears by duration bucket (amount in each ageing bucket)
        $now = now();
        $byDuration = Invoice::where('invoices.organization_id', $organizationId)
            ->where('invoices.status', InvoiceStatus::OVERDUE)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('unit.community', fn($eq) => $eq->where('country', $data['country'])))
            ->selectRaw("
                SUM(CASE WHEN ? - invoices.due_date < 30 THEN GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0)) ELSE 0 END) as under_30,
                SUM(CASE WHEN ? - invoices.due_date >= 30 AND ? - invoices.due_date < 60 THEN GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0)) ELSE 0 END) as d30_60,
                SUM(CASE WHEN ? - invoices.due_date >= 60 AND ? - invoices.due_date < 90 THEN GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0)) ELSE 0 END) as d60_90,
                SUM(CASE WHEN ? - invoices.due_date >= 90 THEN GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0)) ELSE 0 END) as d90_plus
            ", [$now, $now, $now, $now, $now, $now])
            ->first();

        $byDurationData = [
            'under_30' => (float) ($byDuration->under_30 ?? 0),
            'd30_60'   => (float) ($byDuration->d30_60 ?? 0),
            'd60_90'   => (float) ($byDuration->d60_90 ?? 0),
            'd90_plus' => (float) ($byDuration->d90_plus ?? 0),
        ];

        // Arrears by community type
        $byCommunityTypeQuery = Invoice::where('invoices.organization_id', $organizationId)
            ->where('invoices.status', InvoiceStatus::OVERDUE)
            ->join('units', 'units.id', '=', 'invoices.unit_id')
            ->join('communities', 'communities.id', '=', 'units.community_id');
        if (!empty($data['country'])) {
            $byCommunityTypeQuery->where('communities.country', $data['country']);
        }
        $byCommunityType = $byCommunityTypeQuery
            ->selectRaw("communities.entity_type, COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0) as total")
            ->groupBy('communities.entity_type')
            ->pluck('total', 'entity_type')
            ->toArray();

        // Arrears by ledger
        $byLedger = Invoice::where('invoices.organization_id', $organizationId)
            ->where('invoices.status', InvoiceStatus::OVERDUE)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('unit.community', fn($eq) => $eq->where('country', $data['country'])))
            ->join('ledgers', 'ledgers.id', '=', 'invoices.ledger_id')
            ->selectRaw("ledgers.name, COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0) as total")
            ->groupBy('ledgers.name')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'name')
            ->toArray();

        // Top owner arrears (owners with highest overdue amounts)
        $topOwnerArrears = Unit::where('units.organization_id', $organizationId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->join('owners', 'owners.unit_id', '=', 'units.id')
            ->addSelect([
                'units.id',
                'units.unit_number',
                'units.community_id',
                'owners.full_name',
                'debtor_amount' => Invoice::selectRaw(
                    "COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0)"
                )
                    ->whereColumn('invoices.unit_id', 'units.id')
                    ->where('invoices.status', InvoiceStatus::OVERDUE),
            ])
            ->orderByDesc('debtor_amount')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'unit_id'     => $row->id,
                'community_id'   => $row->community_id,
                'name'        => $row->full_name,
                'unit_number' => $row->unit_number,
                'amount'      => (float) $row->debtor_amount,
            ])
            ->toArray();

        // Top occupant arrears (organizations with highest overdue amounts)
        $topOccupantArrears = Unit::where('units.organization_id', $organizationId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->join('occupants', function ($join) {
                $join->on('occupants.unit_id', '=', 'units.id')
                     ->where('occupants.is_active', true);
            })
            ->addSelect([
                'units.id',
                'units.unit_number',
                'units.community_id',
                'occupants.full_name',
                'debtor_amount' => Invoice::selectRaw(
                    "COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0)"
                )
                    ->whereColumn('invoices.unit_id', 'units.id')
                    ->where('invoices.status', InvoiceStatus::OVERDUE),
            ])
            ->orderByDesc('debtor_amount')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'unit_id'     => $row->id,
                'community_id'   => $row->community_id,
                'name'        => $row->full_name,
                'unit_number' => $row->unit_number,
                'amount'      => (float) $row->debtor_amount,
            ])
            ->toArray();

        // Prepare unit data with overdue info for the response
        $unitData = collect($paginated->items())->map(function ($unit) {
            $resource = (new UnitResource($unit))->resolve();
            $resource['overdue_amount']       = (float) ($unit->overdue_amount ?? 0);
            $resource['overdue_count']        = (int) ($unit->overdue_count ?? 0);
            $resource['oldest_overdue_date']  = $unit->oldest_overdue_date;
            return $resource;
        });

        return [
            'data' => $unitData,
            'summary' => [
                'units_in_arrears'       => $unitsInArrears,
                'total_units'            => $totalUnits,
                'total_overdue_amount'   => (float) $totalOverdueAmount,
                'total_overdue_invoices' => $totalOverdueInvoices,
                'arrears_rate'           => $totalUnits > 0 ? round(($unitsInArrears / $totalUnits) * 100, 1) : 0,
                'communities_affected'       => $communitiesAffected,
                'by_community'              => $byCommunity,
                'by_duration'            => $byDurationData,
                'by_community_type'         => $byCommunityType,
                'by_ledger'         => $byLedger,
                'top_owner_arrears'      => $topOwnerArrears,
                'top_occupant_arrears'     => $topOccupantArrears,
            ],
            'communities'      => $communities,
            'ledgers' => $ledgers,
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
            ],
        ];
    }

    /**
     * Override sort to handle arrears-specific fields.
     */
    public function applySortOnQuery(): static
    {
        $sort = $this->request->input('_sort');

        if (!$sort) {
            return $this;
        }

        $parts     = explode(':', $sort, 2);
        $field     = $parts[0] ?? null;
        $direction = isset($parts[1]) && strtolower($parts[1]) === 'desc' ? 'desc' : 'asc';

        switch ($field) {
            case 'unit_number':
                $this->query->orderBy('units.unit_number', $direction);
                break;

            case 'owner_name':
                $this->query
                    ->select('units.*')
                    ->leftJoin('owners', 'owners.unit_id', '=', 'units.id')
                    ->orderBy('owners.full_name', $direction);
                break;

            case 'community_name':
                $this->query
                    ->select('units.*')
                    ->leftJoin('communities as sort_communities', 'sort_communities.id', '=', 'units.community_id')
                    ->orderBy('sort_communities.name', $direction);
                break;

            case 'overdue_amount':
                $this->query->orderBy('overdue_amount', $direction);
                break;

            case 'oldest_overdue':
                $this->query->orderBy('oldest_overdue_date', $direction);
                break;

            default:
                parent::applySortOnQuery();
        }

        return $this;
    }
}
