<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstateResource extends JsonResource
{
    private function computeMonthlyRevenue(): float
    {
        $type        = $this->type instanceof \BackedEnum ? $this->type->value : (string) $this->type;
        $levyRevenue = (float) ($this->admin_fund_amount ?? 0) + (float) ($this->reserve_fund_amount ?? 0);
        $rentRevenue = (float) ($this->units_sum_rent_amount ?? 0);

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
            'organization_id'            => $this->organization_id,
            'name'                 => $this->name,
            'address'              => $this->address,
            'type'                 => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'admin_fund_amount'    => $this->admin_fund_amount,
            'reserve_fund_amount'  => $this->reserve_fund_amount,
            'csos_levy_amount'     => $this->csos_levy_amount,
            'default_rent_amount'  => $this->default_rent_amount,
            'billing_day'          => $this->billing_day,
            'payment_terms_days'   => $this->payment_terms_days,
            'billing_paused'       => (bool) $this->billing_paused,
            'country'                   => $this->country,
            'currency'                  => $this->currency,
            'registration_number'       => $this->registration_number,
            'csos_registration_number'  => $this->csos_registration_number,
            'income_tax_number'         => $this->income_tax_number,
            'is_active'                 => (bool) $this->is_active,
            'created_at'           => $this->created_at?->toDateTimeString(),
            'updated_at'           => $this->updated_at?->toDateTimeString(),

            'units_count'           => (int) ($this->units_count ?? 0),
            'occupied_units_count'  => (int) ($this->occupied_units_count ?? 0),
            'vacant_units_count'    => (int) ($this->vacant_units_count ?? 0),
            'monthly_revenue'       => $this->computeMonthlyRevenue(),
            'owners_count'          => $this->whenCounted('owners'),
            'tenants_count'    => $this->whenCounted('tenants'),
            'invoices_count'        => $this->whenCounted('invoices'),
            'cashbook_entries_count' => $this->whenCounted('cashbookEntries'),

            'units'        => UnitResource::collection($this->whenLoaded('units')),
            'charge_types' => ChargeTypeResource::collection($this->whenLoaded('chargeTypes')),
            'organization'       => OrganizationResource::make($this->whenLoaded('organization')),
        ];
    }
}
