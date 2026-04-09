<?php

namespace App\Services;

use Exception;
use App\Models\Estate;
use App\Models\CashbookEntry;
use App\Models\Invoice;
use App\Models\Unit;
use App\Enums\EstateType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\EstateResource;
use App\Http\Resources\EstateResources;

class EstateService extends BaseService
{
    /**
     * Return a paginated, filtered list of estates for the authenticated tenant.
     *
     * @param array $data
     * @return EstateResources
     */
    public function showEstates(array $data): EstateResources
    {
        $user  = Auth::user();
        $query = Estate::where('tenant_id', $user->tenant_id)
            ->withCount([
                'units',
                'units as occupied_units_count' => fn ($q) => $q->whereIn('occupancy_type', ['owner_occupied', 'tenant_occupied']),
                'units as vacant_units_count'   => fn ($q) => $q->where('occupancy_type', 'vacant'),
            ])
            ->withSum('units', 'rent_amount');

        if (!empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        if (isset($data['is_active'])) {
            $isActive = $data['is_active'] === 'true' || $data['is_active'] === true || $data['is_active'] === 1;
            $query->where('is_active', $isActive);
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Return summary statistics across all estates for the authenticated tenant.
     *
     * @param array $data
     * @return array
     */
    public function showEstatesSummary(array $data): array
    {
        $user     = Auth::user();
        $tenantId = $user->tenant_id;

        $totalEstates = Estate::where('tenant_id', $tenantId)->count();

        $totalUnits = Unit::where('tenant_id', $tenantId)->count();

        $occupied = Unit::where('tenant_id', $tenantId)
            ->whereIn('occupancy_type', ['owner_occupied', 'tenant_occupied'])
            ->count();

        // Monthly revenue: sum of levy_override (where set) + default levy amounts for levy estates,
        // and rent_amount for rental estates. Simplified as sum of rent_amount across all units.
        $monthlyRevenue = Unit::where('tenant_id', $tenantId)
            ->whereNotNull('rent_amount')
            ->sum('rent_amount');

        return [
            'total_estates'   => $totalEstates,
            'total_units'     => $totalUnits,
            'occupied'        => $occupied,
            'monthly_revenue' => (float) $monthlyRevenue,
        ];
    }

    /**
     * Create a new estate and auto-configure its charge types.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createEstate(array $data): array
    {
        $user = Auth::user();

        $estateData = collect($data)
            ->only(['name', 'address', 'type', 'default_levy_amount', 'default_rent_amount', 'billing_day'])
            ->toArray();

        $estate = Estate::create(array_merge($estateData, [
            'tenant_id' => $user->tenant_id,
            'is_active' => true,
        ]));

        // Auto-configure default charge types based on estate type
        (new EstateChargeTypeService())->setupDefaultChargeTypes($estate);

        return $this->showCreatedResource($estate);
    }

    /**
     * Bulk delete estates by an array of IDs.
     *
     * @param array $estateIds
     * @return array
     * @throws Exception
     */
    public function deleteEstates(array $estateIds): array
    {
        $user   = Auth::user();
        $estates = Estate::whereIn('id', $estateIds)
            ->where('tenant_id', $user->tenant_id)
            ->get();

        $total = $estates->count();

        if ($total === 0) {
            throw new Exception('No Estates deleted');
        }

        foreach ($estates as $estate) {
            $estate->delete();
        }

        $label = $total === 1 ? 'Estate' : 'Estates';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Return a single estate resource with computed stats for the detail page.
     *
     * @param Estate $estate
     * @return JsonResponse
     */
    public function showEstate(Estate $estate): JsonResponse
    {
        $estate->loadCount([
            'units as owner_occupied_count'  => fn($q) => $q->where('occupancy_type', 'owner_occupied'),
            'units as tenant_occupied_count' => fn($q) => $q->where('occupancy_type', 'tenant_occupied'),
            'units as vacant_count'          => fn($q) => $q->where('occupancy_type', 'vacant'),
            'units as total_units_count',
        ]);

        $invoiceStatusCounts = Invoice::whereHas('unit', fn($q) => $q->where('estate_id', $estate->id))
            ->selectRaw("
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN status = 'partially_paid' THEN 1 ELSE 0 END) as partial_count
            ")
            ->first();

        // --- Net estate balance (mirrors UnitResource balance formula) ---
        //
        // outstanding  = gross invoice amounts - partial payments already allocated to those invoices.
        //                GREATEST(0,...) prevents a mis-statused invoice from going negative.
        // credits      = unallocated cashbook credit entries for units in this estate.
        // total_balance = credits - outstanding  (negative = estate has net arrears)

        $estateUnitIds = Unit::where('estate_id', $estate->id)->select('id');

        // Subquery: IDs of all outstanding (not yet fully paid) invoices for this estate.
        $outstandingInvoiceIds = Invoice::whereIn('unit_id', $estateUnitIds)
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->select('id');

        // Gross sum of those invoices.
        $totalGrossOutstanding = (float) Invoice::whereIn('unit_id', $estateUnitIds)
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->sum('amount');

        // Cashbook amounts already allocated to those invoices (partial payments).
        $totalPartiallyPaid = (float) CashbookEntry::whereIn('invoice_id', $outstandingInvoiceIds)
            ->sum('amount');

        $totalNetOutstanding = max(0.0, $totalGrossOutstanding - $totalPartiallyPaid);

        // Unallocated credit entries sitting on units in this estate.
        $totalUnallocatedCredits = (float) CashbookEntry::whereIn('unit_id', $estateUnitIds)
            ->whereNull('invoice_id')
            ->where('type', 'credit')
            ->sum('amount');

        // Monthly revenue: levy (for non-vacant) + rent (for tenant_occupied) across all active units
        $units          = Unit::where('estate_id', $estate->id)->where('status', 'active')
            ->select(['occupancy_type', 'levy_override', 'rent_amount'])
            ->get();
        $monthlyRevenue = $units->reduce(function (float $carry, Unit $unit) use ($estate): float {
            $occ = $unit->occupancy_type instanceof \App\Enums\OccupancyType
                ? $unit->occupancy_type->value
                : (string) $unit->occupancy_type;
            if ($occ !== 'vacant') {
                $carry += (float) ($unit->levy_override ?? $estate->default_levy_amount ?? 0);
            }
            if ($occ === 'tenant_occupied') {
                $carry += (float) ($unit->rent_amount ?? 0);
            }
            return $carry;
        }, 0.0);

        return (new EstateResource($estate))
            ->additional([
                'stats' => [
                    'total_units'           => (int) $estate->total_units_count,
                    'owner_occupied_count'  => (int) $estate->owner_occupied_count,
                    'tenant_occupied_count' => (int) $estate->tenant_occupied_count,
                    'vacant_count'          => (int) $estate->vacant_count,
                    'monthly_revenue'       => (float) $monthlyRevenue,
                    'total_balance'         => $totalUnallocatedCredits - $totalNetOutstanding,
                    'invoice_status'        => [
                        'paid'    => (int) ($invoiceStatusCounts?->paid_count ?? 0),
                        'overdue' => (int) ($invoiceStatusCounts?->overdue_count ?? 0),
                        'partial' => (int) ($invoiceStatusCounts?->partial_count ?? 0),
                    ],
                ],
            ])
            ->response();
    }

    /**
     * Update an estate's attributes.
     *
     * @param Estate $estate
     * @param array  $data
     * @return array
     */
    public function updateEstate(Estate $estate, array $data): array
    {
        $fillable = collect($data)
            ->only(['name', 'address', 'type', 'default_levy_amount', 'default_rent_amount', 'billing_day', 'is_active'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $estate->update($fillable);

        return $this->showUpdatedResource($estate);
    }

    /**
     * Delete a single estate.
     *
     * @param Estate $estate
     * @return array
     */
    public function deleteEstate(Estate $estate): array
    {
        $deleted = $estate->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Estate deleted' : 'Estate delete unsuccessful',
        ];
    }
}
