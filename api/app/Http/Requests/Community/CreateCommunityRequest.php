<?php

namespace App\Http\Requests\Community;

use App\Enums\CommunityEntityType;
use App\Models\Community;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Community::class);
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'entity_type'          => ['required', Rule::in(CommunityEntityType::values())],
            'code'                 => ['nullable', 'string', 'max:10'],
            'financial_year_end_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'address'              => ['nullable', 'string', 'max:500'],
            'admin_fund_amount'    => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'reserve_fund_amount'  => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'csos_levy_amount'     => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'default_rent_amount'  => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'billing_day'          => ['nullable', 'integer', 'min:1', 'max:28'],
            'payment_terms_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
            'country'                   => ['nullable', 'string', 'max:3'],
            'currency'                  => ['nullable', 'string', 'max:3'],
            'registration_number'       => ['nullable', 'string', 'max:100'],
            'csos_registration_number'  => ['nullable', 'string', 'max:100'],
            'income_tax_number'         => ['nullable', 'string', 'max:100'],
            'merchant_number'           => ['nullable', 'string', 'max:100'],
            'pdf_passwords'             => ['nullable', 'boolean'],
            'community_manager_id'      => ['nullable', 'integer', Rule::exists('users', 'id')],
            'previous_managing_agent'   => ['nullable', 'string', 'max:255'],
            'opening_balance_date'      => ['nullable', 'date'],
            'user_ids'                  => ['nullable', 'array'],
            'user_ids.*'                => ['integer', Rule::exists('users', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'The community name is required.',
            'name.max'              => 'The community name may not exceed 255 characters.',
            'entity_type.required'  => 'The entity type is required.',
            'entity_type.in'        => 'The entity type must be one of: ' . implode(', ', CommunityEntityType::values()) . '.',
        ];
    }
}
