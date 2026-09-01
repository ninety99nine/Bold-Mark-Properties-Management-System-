<?php

namespace App\Http\Resources;

use App\Enums\CommunityEntityType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityResource extends JsonResource
{
    private function computeMonthlyRevenue(): float
    {
        $basis       = $this->entity_type instanceof CommunityEntityType ? $this->entity_type->billingBasis() : 'levy';
        $levyRevenue = (float) ($this->admin_fund_amount ?? 0) + (float) ($this->reserve_fund_amount ?? 0);
        $rentRevenue = (float) ($this->units_sum_rent_amount ?? 0);

        return match ($basis) {
            'rent'  => $rentRevenue,
            'mixed' => $levyRevenue + $rentRevenue,
            default => $levyRevenue,
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
            'code'                 => $this->code,
            'payment_authorisation_mode' => $this->payment_authorisation_mode instanceof \BackedEnum ? $this->payment_authorisation_mode->value : $this->payment_authorisation_mode,
            'address'              => $this->address,
            'postal_address'       => $this->postal_address,
            'contact_number'       => $this->contact_number,
            'contact_email'        => $this->contact_email,
            'entity_type'          => $this->entity_type instanceof \BackedEnum ? $this->entity_type->value : $this->entity_type,
            'status'               => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'billing_basis'        => $this->entity_type instanceof CommunityEntityType ? $this->entity_type->billingBasis() : null,
            'admin_fund_amount'    => $this->admin_fund_amount,
            'reserve_fund_amount'  => $this->reserve_fund_amount,
            'csos_levy_amount'     => $this->csos_levy_amount,
            'default_rent_amount'  => $this->default_rent_amount,
            'billing_day'             => $this->billing_day,
            'payment_terms_days'      => $this->payment_terms_days,
            'payment_reminder_days'   => $this->payment_reminder_days,
            'billing_paused'          => (bool) $this->billing_paused,
            'country'                   => $this->country,
            'currency'                  => $this->currency,
            'registration_number'       => $this->registration_number,
            'csos_registration_number'  => $this->csos_registration_number,
            'income_tax_number'         => $this->income_tax_number,
            'financial_year_end_month'  => $this->financial_year_end_month,
            'is_vat_registered'         => (bool) $this->is_vat_registered,
            'vat_number'                => $this->vat_number,
            'interest_rate'             => $this->interest_rate,
            'interest_exempt_threshold' => $this->interest_exempt_threshold,
            'ageing_type'               => $this->ageing_type instanceof \BackedEnum ? $this->ageing_type->value : $this->ageing_type,
            'suppress_entity_type'      => (bool) $this->suppress_entity_type,
            'unit_addressing'           => $this->unit_addressing ?? 'unit_no',
            'apply_pq'                  => (bool) $this->apply_pq,
            'interest_period'           => $this->interest_period ?? 'per_annum',
            'transfer_clearance_bank'   => $this->transfer_clearance_bank ?? null,
            'is_active'                 => (bool) $this->is_active,
            'created_at'           => $this->created_at?->toDateTimeString(),
            'updated_at'           => $this->updated_at?->toDateTimeString(),

            'units_count'           => (int) ($this->units_count ?? 0),
            'occupied_units_count'  => (int) ($this->occupied_units_count ?? 0),
            'vacant_units_count'    => (int) ($this->vacant_units_count ?? 0),
            'monthly_revenue'       => $this->computeMonthlyRevenue(),
            'owners_count'          => $this->whenCounted('owners'),
            'occupants_count'    => $this->whenCounted('occupants'),
            'invoices_count'        => $this->whenCounted('invoices'),
            'cashbook_entries_count' => $this->whenCounted('cashbookEntries'),

            'units'        => UnitResource::collection($this->whenLoaded('units')),
            'ledgers' => LedgerResource::collection($this->whenLoaded('ledgers')),
            'organization'       => OrganizationResource::make($this->whenLoaded('organization')),
        ];
    }
}
