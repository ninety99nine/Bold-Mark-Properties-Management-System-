<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstateResource extends JsonResource
{
    private function computeMonthlyRevenue(): float
    {
        $type        = $this->type instanceof \BackedEnum ? $this->type->value : (string) $this->type;
        $unitCount   = (int) ($this->units_count ?? 0);
        $defaultLevy = (float) ($this->default_levy_amount ?? 0);
        $rentRevenue = (float) ($this->units_sum_rent_amount ?? 0);
        $levyRevenue = $unitCount * $defaultLevy;

        return match ($type) {
            'sectional_title'    => $levyRevenue,
            'residential_rental' => $rentRevenue,
            'commercial_rental'  => $rentRevenue,
            'mixed'              => $levyRevenue + $rentRevenue,
            default              => 0.0,
        };
    }

    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'tenant_id'            => $this->tenant_id,
            'name'                 => $this->name,
            'address'              => $this->address,
            'type'                 => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'default_levy_amount'  => $this->default_levy_amount,
            'default_rent_amount'  => $this->default_rent_amount,
            'billing_day'          => $this->billing_day,
            'is_active'            => (bool) $this->is_active,
            'created_at'           => $this->created_at?->toDateTimeString(),
            'updated_at'           => $this->updated_at?->toDateTimeString(),

            'units_count'           => (int) ($this->units_count ?? 0),
            'occupied_units_count'  => (int) ($this->occupied_units_count ?? 0),
            'vacant_units_count'    => (int) ($this->vacant_units_count ?? 0),
            'monthly_revenue'       => $this->computeMonthlyRevenue(),
            'owners_count'          => $this->whenCounted('owners'),
            'unit_tenants_count'    => $this->whenCounted('unitTenants'),
            'invoices_count'        => $this->whenCounted('invoices'),
            'cashbook_entries_count' => $this->whenCounted('cashbookEntries'),

            'units'        => UnitResource::collection($this->whenLoaded('units')),
            'charge_types' => ChargeTypeResource::collection($this->whenLoaded('chargeTypes')),
            'tenant'       => TenantResource::make($this->whenLoaded('tenant')),
        ];
    }
}
