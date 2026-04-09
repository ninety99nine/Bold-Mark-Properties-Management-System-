<?php

namespace App\Http\Requests\Invoice;

use App\Enums\BilledToType;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceRequest extends FormRequest
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
            'unit_id'        => ['required', 'uuid', 'exists:units,id'],
            'charge_type_id' => ['required', 'uuid', 'exists:charge_types,id'],
            'billed_to_type' => ['required', Rule::in(BilledToType::values())],
            'billed_to_id'   => ['required', 'uuid'],
            'amount'         => ['required', 'numeric', 'min:0'],
            'billing_period' => ['required', 'date'],
            'due_date'       => ['required', 'date'],
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
            'unit_id.required'        => 'The unit is required.',
            'unit_id.uuid'            => 'The unit ID must be a valid UUID.',
            'unit_id.exists'          => 'The selected unit does not exist.',
            'charge_type_id.required' => 'The charge type is required.',
            'charge_type_id.uuid'     => 'The charge type ID must be a valid UUID.',
            'charge_type_id.exists'   => 'The selected charge type does not exist.',
            'billed_to_type.required' => 'The billed to type is required.',
            'billed_to_type.in'       => 'The billed to type must be one of: ' . implode(', ', BilledToType::values()) . '.',
            'billed_to_id.required'   => 'The billed to ID is required.',
            'billed_to_id.uuid'       => 'The billed to ID must be a valid UUID.',
            'amount.required'         => 'The amount is required.',
            'amount.numeric'          => 'The amount must be a number.',
            'amount.min'              => 'The amount must be at least zero.',
            'billing_period.required' => 'The billing period is required.',
            'billing_period.date'     => 'The billing period must be a valid date.',
            'due_date.required'       => 'The due date is required.',
            'due_date.date'           => 'The due date must be a valid date.',
        ];
    }
}
