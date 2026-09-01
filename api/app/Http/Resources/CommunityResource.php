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
            'merchant_number'           => $this->merchant_number,
            'pdf_passwords'             => (bool) $this->pdf_passwords,
            'community_manager_id'      => $this->community_manager_id,
            'previous_managing_agent'   => $this->previous_managing_agent,
            'opening_balance_date'      => $this->opening_balance_date?->toDateString(),
            'community_manager'         => $this->whenLoaded('communityManager', fn () => $this->communityManager ? [
                'id'    => $this->communityManager->id,
                'name'  => $this->communityManager->name,
                'email' => $this->communityManager->email,
            ] : null),
            'assigned_user_ids'         => $this->whenLoaded('assignedUsers', fn () => $this->assignedUsers->pluck('id')->all()),
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

            // ── Communicate → Settings: per-community email branding ───────
            'email_logo_url'            => $this->email_logo_url,
            'email_header_url'          => $this->email_header_url,
            'email_footer_url'          => $this->email_footer_url,

            // ── Community-info modal: Information + Admin Charges tabs ──────
            // Recovery flags live on the billing setup (Default Billing Setup page).
            'water_recovery'            => (bool) ($this->relationLoaded('billingSetup') && $this->billingSetup ? $this->billingSetup->water_recovery : false),
            'electricity_recovery'      => (bool) ($this->relationLoaded('billingSetup') && $this->billingSetup ? $this->billingSetup->electricity_recovery : false),
            'penalty_admin_fee'         => $this->penalty_admin_fee,
            'warning_admin_fee'         => $this->warning_admin_fee,
            'transfer_clearance_fee'    => $this->transfer_clearance_fee,
            'phonecall_fee'             => $this->phonecall_fee,
            'apply_debt_collection_fee' => (bool) $this->apply_debt_collection_fee,
            'handed_over_fee'           => $this->handed_over_fee,
            'notice_threshold_amount'   => $this->notice_threshold_amount,
            'notice_charges'            => $this->notice_charges,
            'notices_exemption'         => $this->notices_exemption ?? [],

            // ── Community-info modal: Bank Details + Trustees tabs ─────────
            'bank_accounts'             => $this->whenLoaded('bankAccounts', fn () => $this->bankAccounts->map(fn ($b) => [
                'id'             => $b->id,
                'name'           => $b->name,
                'bank_name'      => $b->bank_name,
                'account_number' => $b->account_number,
                'branch_code'    => $b->branch_code,
                'branch_name'    => $b->branch_name,
                'integration'    => $b->integration,
                'type'           => $b->type instanceof \BackedEnum ? $b->type->value : $b->type,
            ])->values()),
            'directors_trustees'        => $this->whenLoaded('members', fn () => $this->members
                ->where('is_director_trustee', true)
                ->map(fn ($m) => [
                    'id'        => $m->id,
                    'name'      => $m->name,
                    'email'     => $m->email,
                    'cellphone' => $m->cellphone,
                ])->values()),

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
