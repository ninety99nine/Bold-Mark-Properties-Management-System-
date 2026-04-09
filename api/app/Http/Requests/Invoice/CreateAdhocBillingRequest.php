<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class CreateAdhocBillingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estate_id'      => ['required', 'uuid', 'exists:estates,id'],
            'charge_type_id' => ['required', 'uuid', 'exists:charge_types,id'],
            'unit_ids'       => ['sometimes', 'array'],
            'unit_ids.*'     => ['uuid', 'exists:units,id'],
            'amount'         => ['required', 'numeric', 'min:0'],
            'billing_period' => ['required', 'date_format:Y-m'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estate_id.required'         => 'The estate is required.',
            'estate_id.uuid'             => 'The estate ID must be a valid UUID.',
            'estate_id.exists'           => 'The selected estate does not exist.',
            'charge_type_id.required'    => 'The charge type is required.',
            'charge_type_id.uuid'        => 'The charge type ID must be a valid UUID.',
            'charge_type_id.exists'      => 'The selected charge type does not exist.',
            'unit_ids.array'             => 'The unit IDs must be provided as an array.',
            'unit_ids.*.uuid'            => 'Each unit ID must be a valid UUID.',
            'unit_ids.*.exists'          => 'One or more of the selected units do not exist.',
            'amount.required'            => 'The amount is required.',
            'amount.numeric'             => 'The amount must be a number.',
            'amount.min'                 => 'The amount must be at least zero.',
            'billing_period.required'    => 'The billing period is required.',
            'billing_period.date_format' => 'The billing period must be in Y-m format (e.g. 2026-04).',
        ];
    }
}
