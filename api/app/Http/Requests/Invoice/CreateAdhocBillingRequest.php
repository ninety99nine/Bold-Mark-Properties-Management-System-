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
            'community_id'      => ['required', 'uuid', 'exists:communities,id'],
            'ledger_id' => ['required', 'uuid', 'exists:ledgers,id'],
            'unit_ids'       => ['sometimes', 'array'],
            'unit_ids.*'     => ['uuid', 'exists:units,id'],
            'amount'         => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
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
            'community_id.required'         => 'The community is required.',
            'community_id.uuid'             => 'The community ID must be a valid UUID.',
            'community_id.exists'           => 'The selected community does not exist.',
            'ledger_id.required'    => 'The ledger is required.',
            'ledger_id.uuid'        => 'The ledger ID must be a valid UUID.',
            'ledger_id.exists'      => 'The selected ledger does not exist.',
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
