<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\Community;
use App\Http\Resources\UnitResource;
use Illuminate\Support\Facades\Auth;

class VacancyService extends BaseService
{
    /**
     * Return paginated vacant units across all communities for the authenticated occupant,
     * along with summary stats for the vacancies page.
     *
     * Supported query parameters:
     *   _search           → unit_number, owner name, or community name
     *   _sort             → unit_number:asc/desc | owner_name:asc/desc | community_name:asc/desc
     *   _date_range       → today | this_week | this_month | this_year | custom | all_time
     *   _date_range_start → Y-m-d
     *   _date_range_end   → Y-m-d
     *   community_id         → filter to a specific community
     *   community_type       → sectional_title | residential_rental | commercial_rental | mixed
     *   _per_page         → pagination size (default 15)
     *
     * @param array $data
     * @return array
     */
    public function showVacancies(array $data): array
    {
        $user = Auth::user();

        // ── Build the query for vacant units across all communities ──────────
        $query = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->with(['owner', 'community:id,name,entity_type,address,admin_fund_amount,default_rent_amount']);

        // Filter by country (scoped portfolio)
        if (!empty($data['country'])) {
            $query->whereHas('community', fn($q) => $q->where('country', $data['country']));
        }

        // Filter by specific community
        if (!empty($data['community_id'])) {
            $query->where('units.community_id', $data['community_id']);
        }

        // Filter by community entity type (join communities table)
        if (!empty($data['community_type'])) {
            $query->whereHas('community', function ($q) use ($data) {
                $q->where('entity_type', $data['community_type']);
            });
        }

        // Search across unit number, owner name, and community name
        if (!empty($data['_search'])) {
            $search = $data['_search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('units.unit_number', $search)
                  ->orWhereHas('owner', fn($o) => $o->whereLike('full_name', $search))
                  ->orWhereHas('community', fn($e) => $e->whereLike('name', $search));
            });
        }

        // Default sort
        if (!request()->has('_sort')) {
            $query->orderBy('units.created_at', 'desc');
        }

        $this->setQuery($query);
        $this->applyDateRangeFromRequest();
        $this->applySortOnQuery();

        $perPage   = max(1, (int) request()->input('_per_page', $this->defaultPerPage));
        $paginated = $this->query->paginate($perPage)->withQueryString();

        // ── Summary stats ───────────────────────────────────────────────
        $totalVacant = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        $totalUnits = Unit::where('units.organization_id', $user->organization_id)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        $vacancyRate = $totalUnits > 0 ? round(($totalVacant / $totalUnits) * 100, 1) : 0;

        // Vacant units grouped by community type
        $byCommunityTypeQuery = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->join('communities', 'communities.id', '=', 'units.community_id');
        if (!empty($data['country'])) {
            $byCommunityTypeQuery->where('communities.country', $data['country']);
        }
        $byCommunityType = $byCommunityTypeQuery
            ->selectRaw("communities.entity_type, COUNT(*) as count")
            ->groupBy('communities.entity_type')
            ->pluck('count', 'entity_type')
            ->toArray();

        // Count of distinct communities with vacant units (uncapped)
        $communitiesAffected = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
            ->distinct('community_id')
            ->count('community_id');

        // Communities with the most vacancies (top 5)
        $byCommunityQuery = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->join('communities', 'communities.id', '=', 'units.community_id');
        if (!empty($data['country'])) {
            $byCommunityQuery->where('communities.country', $data['country']);
        }
        $byCommunity = $byCommunityQuery
            ->selectRaw("communities.id, communities.name, communities.entity_type, COUNT(*) as vacant_count")
            ->groupBy('communities.id', 'communities.name', 'communities.entity_type')
            ->orderByDesc('vacant_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id'           => $row->id,
                'name'         => $row->name,
                'entity_type'  => $row->entity_type,
                'vacant_count' => (int) $row->vacant_count,
            ])
            ->toArray();

        // ── Chart data ──────────────────────────────────────────────────

        // Occupancy breakdown per community (vacant vs occupied) — all communities
        $occupancyPerCommunityQuery = Community::where('communities.organization_id', $user->organization_id);
        if (!empty($data['country'])) {
            $occupancyPerCommunityQuery->where('communities.country', $data['country']);
        }
        $occupancyPerCommunity = $occupancyPerCommunityQuery
            ->join('units', 'units.community_id', '=', 'communities.id')
            ->selectRaw("
                communities.id,
                communities.name,
                SUM(CASE WHEN units.occupancy_type = 'vacant' THEN 1 ELSE 0 END) as vacant,
                SUM(CASE WHEN units.occupancy_type != 'vacant' THEN 1 ELSE 0 END) as occupied
            ")
            ->groupBy('communities.id', 'communities.name')
            ->orderBy('communities.name')
            ->get()
            ->map(fn ($row) => [
                'id'       => $row->id,
                'name'     => $row->name,
                'vacant'   => (int) $row->vacant,
                'occupied' => (int) $row->occupied,
            ])
            ->toArray();

        // Estimated lost revenue from vacant units (levy + rent defaults not being collected)
        $lostRevenueQuery = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->join('communities', 'communities.id', '=', 'units.community_id');
        if (!empty($data['country'])) {
            $lostRevenueQuery->where('communities.country', $data['country']);
        }
        $lostRevenue = $lostRevenueQuery
            ->selectRaw("
                communities.id,
                communities.name,
                COALESCE(SUM(
                    COALESCE(units.levy_override, communities.admin_fund_amount, 0) +
                    COALESCE(units.rent_amount, communities.default_rent_amount, 0)
                ), 0) as lost_monthly
            ")
            ->groupBy('communities.id', 'communities.name')
            ->orderByDesc('lost_monthly')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id'           => $row->id,
                'name'         => $row->name,
                'lost_monthly' => (float) $row->lost_monthly,
            ])
            ->toArray();

        $totalLostRevenue = array_sum(array_column($lostRevenue, 'lost_monthly'));

        // Vacancy duration — how long units have been vacant (based on updated_at when set to vacant)
        // EXTRACT(EPOCH FROM ...) is Postgres-specific; fall back to zeros on other drivers.
        $byDuration = ['under_30' => 0, 'd30_90' => 0, 'd90_180' => 0, 'd180_plus' => 0];
        try {
            $now = now();
            $durationBuckets = Unit::where('units.occupancy_type', 'vacant')
                ->where('units.organization_id', $user->organization_id)
                ->when(!empty($data['country']), fn($q) => $q->whereHas('community', fn($eq) => $eq->where('country', $data['country'])))
                ->selectRaw("
                    SUM(CASE WHEN EXTRACT(EPOCH FROM (? - units.updated_at)) / 86400 < 30 THEN 1 ELSE 0 END) as under_30,
                    SUM(CASE WHEN EXTRACT(EPOCH FROM (? - units.updated_at)) / 86400 >= 30 AND EXTRACT(EPOCH FROM (? - units.updated_at)) / 86400 < 90 THEN 1 ELSE 0 END) as d30_90,
                    SUM(CASE WHEN EXTRACT(EPOCH FROM (? - units.updated_at)) / 86400 >= 90 AND EXTRACT(EPOCH FROM (? - units.updated_at)) / 86400 < 180 THEN 1 ELSE 0 END) as d90_180,
                    SUM(CASE WHEN EXTRACT(EPOCH FROM (? - units.updated_at)) / 86400 >= 180 THEN 1 ELSE 0 END) as d180_plus
                ", [$now, $now, $now, $now, $now, $now])
                ->first();

            $byDuration = [
                'under_30'  => (int) ($durationBuckets->under_30 ?? 0),
                'd30_90'    => (int) ($durationBuckets->d30_90 ?? 0),
                'd90_180'   => (int) ($durationBuckets->d90_180 ?? 0),
                'd180_plus' => (int) ($durationBuckets->d180_plus ?? 0),
            ];
        } catch (\Exception $e) {
            // falls back to zeros initialized above
        }

        // Communities dropdown for filter
        $communitiesQuery = Community::where('organization_id', $user->organization_id);
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

        return [
            'data' => UnitResource::collection($paginated->items()),
            'summary' => [
                'total_vacant'        => $totalVacant,
                'total_units'         => $totalUnits,
                'vacancy_rate'        => $vacancyRate,
                'communities_affected'    => $communitiesAffected,
                'by_community_type'      => $byCommunityType,
                'by_community'           => $byCommunity,
                'occupancy_per_community'=> $occupancyPerCommunity,
                'lost_revenue'        => $lostRevenue,
                'total_lost_revenue'  => $totalLostRevenue,
                'by_duration'         => $byDuration,
            ],
            'communities' => $communities,
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
            ],
        ];
    }

    /**
     * Override sort to handle cross-community fields.
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
                    ->leftJoin('communities', 'communities.id', '=', 'units.community_id')
                    ->orderBy('communities.name', $direction);
                break;

            default:
                parent::applySortOnQuery();
        }

        return $this;
    }
}
