<?php

namespace App\Http\Requests\Estate;

use App\Enums\EstateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('estate'));
    }

    public function rules(): array
    {
        return [
            'name'                 => ['sometimes', 'string', 'max:255'],
            'type'                 => ['sometimes', Rule::in(EstateType::values())],
            'address'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'admin_fund_amount'    => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'reserve_fund_amount'  => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'csos_levy_amount'     => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'default_rent_amount'  => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'billing_day'          => ['sometimes', 'nullable', 'integer', 'min:1', 'max:28'],
            'payment_terms_days'   => ['sometimes', 'integer', 'min:1', 'max:365'],
            'billing_paused'       => ['sometimes', 'boolean'],
            'country'                   => ['sometimes', 'nullable', 'string', 'max:3'],
            'currency'                  => ['sometimes', 'nullable', 'string', 'max:3'],
            'registration_number'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'csos_registration_number'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'income_tax_number'         => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The estate type must be one of: ' . implode(', ', EstateType::values()) . '.',
        ];
    }
}
