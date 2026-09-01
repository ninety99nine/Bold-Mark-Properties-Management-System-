<?php

namespace App\Http\Requests\Community;

use App\Enums\AgeingType;
use App\Enums\CommunityEntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('community'));
    }

    public function rules(): array
    {
        return [
            'name'                 => ['sometimes', 'string', 'max:255'],
            'entity_type'          => ['sometimes', Rule::in(CommunityEntityType::values())],
            'address'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'postal_address'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'contact_number'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'contact_email'        => ['sometimes', 'nullable', 'email', 'max:255'],
            'admin_fund_amount'    => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'reserve_fund_amount'  => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'csos_levy_amount'     => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'default_rent_amount'  => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'billing_day'             => ['sometimes', 'nullable', 'integer', 'min:1', 'max:28'],
            'payment_terms_days'      => ['sometimes', 'integer', 'min:1', 'max:365'],
            'payment_reminder_days'   => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'billing_paused'          => ['sometimes', 'boolean'],
            'country'                   => ['sometimes', 'nullable', 'string', 'max:3'],
            'currency'                  => ['sometimes', 'nullable', 'string', 'max:3'],
            'registration_number'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'csos_registration_number'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'income_tax_number'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'code'                       => ['sometimes', 'nullable', 'string', 'max:10'],
            'financial_year_end_month'   => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
            'is_vat_registered'          => ['sometimes', 'boolean'],
            'vat_number'                 => ['sometimes', 'nullable', 'string', 'max:100'],
            'interest_rate'              => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'interest_exempt_threshold'  => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'ageing_type'                => ['sometimes', 'nullable', Rule::in(AgeingType::values())],

            // WeConnectU "Setup → General" additions
            'suppress_entity_type'       => ['sometimes', 'boolean'],
            'unit_addressing'            => ['sometimes', 'nullable', Rule::in(['unit_no', 'door_no', 'section_no'])],
            'apply_pq'                   => ['sometimes', 'boolean'],
            'interest_period'            => ['sometimes', 'nullable', Rule::in(['per_annum', 'per_month'])],
            'transfer_clearance_bank'                => ['sometimes', 'nullable', 'array'],
            'transfer_clearance_bank.account_holder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'transfer_clearance_bank.bank_name'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'transfer_clearance_bank.account_type'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'transfer_clearance_bank.account_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'transfer_clearance_bank.branch_code'    => ['sometimes', 'nullable', 'string', 'max:20'],
            'transfer_clearance_bank.branch_name'    => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'entity_type.in' => 'The entity type must be one of: ' . implode(', ', CommunityEntityType::values()) . '.',
            'ageing_type.in' => 'The ageing type must be one of: ' . implode(', ', AgeingType::values()) . '.',
        ];
    }
}
