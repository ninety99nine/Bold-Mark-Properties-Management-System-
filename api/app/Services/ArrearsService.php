<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\CashbookEntry;
use App\Enums\InvoiceStatus;
use App\Http\Resources\UnitResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArrearsService extends BaseService
{
    /**
     * Return paginated units with overdue invoices across all estates,
     * along with summary stats for the arrears page.
     *
     * A unit is "in arrears" when it has at least one invoice with status = overdue.
     *
     * Supported query parameters:
     *   _search           → unit_number, owner name, or estate name
     *   _sort             → unit_number:asc/desc | owner_name:asc/desc | estate_name:asc/desc
     *                       | overdue_amount:asc/desc | oldest_overdue:asc/desc
     *   _date_range       → today | this_week | this_month | this_year | custom | all_time
     *   _date_range_start → Y-m-d
     *   _date_range_end   → Y-m-d
     *   estate_id         → filter to a specific estate
     *   estate_type       → sectional_title | residential_rental | commercial_rental | mixed
     *   charge_type       → filter by charge type code (LEVY, RENT, etc.)
     *   _per_page         → pagination size (default 15)
     *
     * @param array $data
     * @return array
     */
    public function showArrears(array $data): array
    {
        $user = Auth::user();
        $tenantId = $user->organization_id;

        // ── Units with overdue invoices ─────────────────────────────────
        $query = Unit::where('units.organization_id', $tenantId)
            ->with(['owner', 'currentTenant', 'estate:id,name,type,address'])
            ->whereHas('invoices', function ($q) use ($data) {
                $q->where('status', InvoiceStatus::OVERDUE);
                if (!empty($data['charge_type'])) {
                    $q->whereHas('chargeType', fn($ct) => $ct->where('code', $data['charge_type']));
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
            $query->whereHas('estate', fn($q) => $q->where('country', $data['country']));
        }

        // Filter by estate
        if (!empty($data['estate_id'])) {
            $query->where('units.estate_id', $data['estate_id']);
        }

        // Filter by estate type
        if (!empty($data['estate_type'])) {
            $query->whereHas('estate', fn($q) => $q->where('type', $data['estate_type']));
        }

        // Search
        if (!empty($data['_search'])) {
            $search = $data['_search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('units.unit_number', $search)
                  ->orWhereHas('owner', fn($o) => $o->whereLike('full_name', $search))
                  ->orWhereHas('estate', fn($e) => $e->whereLike('name', $search));
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
        $unitsInArrears = Unit::where('units.organization_id', $tenantId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        $totalUnits = Unit::where('organization_id', $tenantId)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        $totalOverdueAmount = Invoice::where('invoices.organization_id', $tenantId)
            ->where('invoices.status', InvoiceStatus::OVERDUE)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('unit.estate', fn($eq) => $eq->where('country', $data['country'])))
            ->selectRaw(
                "COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0) as total"
            )
            ->value('total');

        $totalOverdueInvoices = Invoice::where('organization_id', $tenantId)
            ->where('status', InvoiceStatus::OVERDUE)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('unit.estate', fn($eq) => $eq->where('country', $data['country'])))
            ->count();

        // Count of distinct estates with overdue units (uncapped)
        $estatesAffected = Unit::where('units.organization_id', $tenantId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->distinct('estate_id')
            ->count('estate_id');

        // Breakdown by estate (top 5 by overdue amount)
        $byEstate = Unit::where('units.organization_id', $tenantId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->join('estates', 'estates.id', '=', 'units.estate_id')
            ->join('invoices', function ($join) {
                $join->on('invoices.unit_id', '=', 'units.id')
                     ->where('invoices.status', InvoiceStatus::OVERDUE);
            })
            ->selectRaw("estates.id, estates.name, estates.type, COUNT(DISTINCT units.id) as units_count, COALESCE(SUM(invoices.amount), 0) as overdue_total")
            ->groupBy('estates.id', 'estates.name', 'estates.type')
            ->orderByDesc('overdue_total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id'            => $row->id,
                'name'          => $row->name,
                'type'          => $row->type,
                'units_count'   => (int) $row->units_count,
                'overdue_total' => (float) $row->overdue_total,
            ])
            ->toArray();

        // Estates dropdown for filter
        $estatesQuery = Estate::where('organization_id', $tenantId);
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

        // Charge types for filter
        $chargeTypes = DB::table('charge_types')
            ->where('organization_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'code', 'name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($ct) => [
                'id'   => $ct->id,
                'code' => $ct->code,
                'name' => $ct->name,
            ])
            ->toArray();

        // ── Chart data ──────────────────────────────────────────────────

        // Arrears by duration bucket (amount in each ageing bucket)
        $now = now();
        $byDuration = Invoice::where('invoices.organization_id', $tenantId)
            ->where('invoices.status', InvoiceStatus::OVERDUE)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('unit.estate', fn($eq) => $eq->where('country', $data['country'])))
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

        // Arrears by estate type
        $byEstateTypeQuery = Invoice::where('invoices.organization_id', $tenantId)
            ->where('invoices.status', InvoiceStatus::OVERDUE)
            ->join('units', 'units.id', '=', 'invoices.unit_id')
            ->join('estates', 'estates.id', '=', 'units.estate_id');
        if (!empty($data['country'])) {
            $byEstateTypeQuery->where('estates.country', $data['country']);
        }
        $byEstateType = $byEstateTypeQuery
            ->selectRaw("estates.type, COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0) as total")
            ->groupBy('estates.type')
            ->pluck('total', 'type')
            ->toArray();

        // Arrears by charge type
        $byChargeType = Invoice::where('invoices.organization_id', $tenantId)
            ->where('invoices.status', InvoiceStatus::OVERDUE)
            ->when(!empty($data['country']), fn($q) => $q->whereHas('unit.estate', fn($eq) => $eq->where('country', $data['country'])))
            ->join('charge_types', 'charge_types.id', '=', 'invoices.charge_type_id')
            ->selectRaw("charge_types.name, COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0) as total")
            ->groupBy('charge_types.name')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'name')
            ->toArray();

        // Top owner arrears (owners with highest overdue amounts)
        $topOwnerArrears = Unit::where('units.organization_id', $tenantId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->join('owners', 'owners.unit_id', '=', 'units.id')
            ->addSelect([
                'units.id',
                'units.unit_number',
                'units.estate_id',
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
                'estate_id'   => $row->estate_id,
                'name'        => $row->full_name,
                'unit_number' => $row->unit_number,
                'amount'      => (float) $row->debtor_amount,
            ])
            ->toArray();

        // Top tenant arrears (organizations with highest overdue amounts)
        $topTenantArrears = Unit::where('units.organization_id', $tenantId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->when(!empty($data['country']), fn($q) => $q->whereHas('estate', fn($eq) => $eq->where('country', $data['country'])))
            ->join('tenants', function ($join) {
                $join->on('tenants.unit_id', '=', 'units.id')
                     ->where('tenants.is_active', true);
            })
            ->addSelect([
                'units.id',
                'units.unit_number',
                'units.estate_id',
                'tenants.full_name',
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
                'estate_id'   => $row->estate_id,
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
                'estates_affected'       => $estatesAffected,
                'by_estate'              => $byEstate,
                'by_duration'            => $byDurationData,
                'by_estate_type'         => $byEstateType,
                'by_charge_type'         => $byChargeType,
                'top_owner_arrears'      => $topOwnerArrears,
                'top_tenant_arrears'     => $topTenantArrears,
            ],
            'estates'      => $estates,
            'charge_types' => $chargeTypes,
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

            case 'estate_name':
                $this->query
                    ->select('units.*')
                    ->leftJoin('estates as sort_estates', 'sort_estates.id', '=', 'units.estate_id')
                    ->orderBy('sort_estates.name', $direction);
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
