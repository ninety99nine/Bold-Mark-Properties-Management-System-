<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\Estate;
use App\Http\Resources\UnitResource;
use Illuminate\Support\Facades\Auth;

class VacancyService extends BaseService
{
    /**
     * Return paginated vacant units across all estates for the authenticated tenant,
     * along with summary stats for the vacancies page.
     *
     * Supported query parameters:
     *   _search           → unit_number, owner name, or estate name
     *   _sort             → unit_number:asc/desc | owner_name:asc/desc | estate_name:asc/desc
     *   _date_range       → today | this_week | this_month | this_year | custom | all_time
     *   _date_range_start → Y-m-d
     *   _date_range_end   → Y-m-d
     *   estate_id         → filter to a specific estate
     *   estate_type       → sectional_title | residential_rental | commercial_rental | mixed
     *   _per_page         → pagination size (default 15)
     *
     * @param array $data
     * @return array
     */
    public function showVacancies(array $data): array
    {
        $user = Auth::user();

        // ── Build the query for vacant units across all estates ──────────
        $query = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->with(['owner', 'estate:id,name,type,address,default_levy_amount,default_rent_amount']);

        // Filter by country (scoped portfolio)
        if (!empty($data['country'])) {
            $query->whereHas('estate', fn($q) => $q->where('country', $data['country']));
        }

        // Filter by specific estate
        if (!empty($data['estate_id'])) {
            $query->where('units.estate_id', $data['estate_id']);
        }

        // Filter by estate type (join estates table)
        if (!empty($data['estate_type'])) {
            $query->whereHas('estate', function ($q) use ($data) {
                $q->where('type', $data['estate_type']);
            });
        }

        // Search across unit number, owner name, and estate name
        if (!empty($data['_search'])) {
            $search = $data['_search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('units.unit_number', $search)
                  ->orWhereHas('owner', fn($o) => $o->whereLike('full_name', $search))
                  ->orWhereHas('estate', fn($e) => $e->whereLike('name', $search));
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
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        $totalUnits = Unit::where('units.organization_id', $user->organization_id)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        $vacancyRate = $totalUnits > 0 ? round(($totalVacant / $totalUnits) * 100, 1) : 0;

        // Vacant units grouped by estate type
        $byEstateTypeQuery = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->join('estates', 'estates.id', '=', 'units.estate_id');
        if (!empty($data['country'])) {
            $byEstateTypeQuery->where('estates.country', $data['country']);
        }
        $byEstateType = $byEstateTypeQuery
            ->selectRaw("estates.type, COUNT(*) as count")
            ->groupBy('estates.type')
            ->pluck('count', 'type')
            ->toArray();

        // Count of distinct estates with vacant units (uncapped)
        $estatesAffected = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->distinct('estate_id')
            ->count('estate_id');

        // Estates with the most vacancies (top 5)
        $byEstateQuery = Unit::where('units.occupancy_type', 'vacant')
            ->where('units.organization_id', $user->organization_id)
            ->join('estates', 'estates.id', '=', 'units.estate_id');
        if (!empty($data['country'])) {
            $byEstateQuery->where('estates.country', $data['country']);
        }
        $byEstate = $byEstateQuery
            ->selectRaw("estates.id, estates.name, estates.type, COUNT(*) as vacant_count")
            ->groupBy('estates.id', 'estates.name', 'estates.type')
            ->orderByDesc('vacant_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id'           => $row->id,
                'name'         => $row->name,
                'type'         => $row->type,
                'vacant_count' => (int) $row->vacant_count,
            ])
            ->toArray();

        // ── Chart data ──────────────────────────────────────────────────

        // Occupancy breakdown per estate (vacant vs occupied) — all estates
        $occupancyPerEstateQuery = Estate::where('estates.organization_id', $user->organization_id);
        if (!empty($data['country'])) {
            $occupancyPerEstateQuery->where('estates.country', $data['country']);
        }
        $occupancyPerEstate = $occupancyPerEstateQuery
            ->join('units', 'units.estate_id', '=', 'estates.id')
            ->selectRaw("
                estates.id,
                estates.name,
                SUM(CASE WHEN units.occupancy_type = 'vacant' THEN 1 ELSE 0 END) as vacant,
                SUM(CASE WHEN units.occupancy_type != 'vacant' THEN 1 ELSE 0 END) as occupied
            ")
            ->groupBy('estates.id', 'estates.name')
            ->orderBy('estates.name')
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
            ->join('estates', 'estates.id', '=', 'units.estate_id');
        if (!empty($data['country'])) {
            $lostRevenueQuery->where('estates.country', $data['country']);
        }
        $lostRevenue = $lostRevenueQuery
            ->selectRaw("
                estates.id,
                estates.name,
                COALESCE(SUM(
                    COALESCE(units.levy_override, estates.default_levy_amount, 0) +
                    COALESCE(units.rent_amount, estates.default_rent_amount, 0)
                ), 0) as lost_monthly
            ")
            ->groupBy('estates.id', 'estates.name')
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
                ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
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

        // Estates dropdown for filter
        $estatesQuery = Estate::where('organization_id', $user->organization_id);
        if (!empty($data['country'])) {
            $estatesQuery->where('country', $data['country']);
        }
        $estates = $estatesQuery
            ->select('id', 'name', 'type')
            ->orderBy('name')
            ->get()
            ->map(fn ($e) => [
                'id'   => $e->id,
                'name' => $e->name,
                'type' => $e->type instanceof \BackedEnum ? $e->type->value : $e->type,
            ])
            ->toArray();

        return [
            'data' => UnitResource::collection($paginated->items()),
            'summary' => [
                'total_vacant'        => $totalVacant,
                'total_units'         => $totalUnits,
                'vacancy_rate'        => $vacancyRate,
                'estates_affected'    => $estatesAffected,
                'by_estate_type'      => $byEstateType,
                'by_estate'           => $byEstate,
                'occupancy_per_estate'=> $occupancyPerEstate,
                'lost_revenue'        => $lostRevenue,
                'total_lost_revenue'  => $totalLostRevenue,
                'by_duration'         => $byDuration,
            ],
            'estates' => $estates,
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
            ],
        ];
    }

    /**
     * Override sort to handle cross-estate fields.
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

            case 'estate_name':
                $this->query
                    ->select('units.*')
                    ->leftJoin('estates', 'estates.id', '=', 'units.estate_id')
                    ->orderBy('estates.name', $direction);
                break;

            default:
                parent::applySortOnQuery();
        }

        return $this;
    }
}
