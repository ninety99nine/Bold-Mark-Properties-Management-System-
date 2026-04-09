<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status'         => ['sometimes', Rule::in(InvoiceStatus::values())],
            'due_date'       => ['sometimes', 'date'],
            'amount'         => ['sometimes', 'numeric', 'min:0'],
            'billing_period' => ['sometimes', 'date'],
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
            'status.in'           => 'The status must be one of: ' . implode(', ', InvoiceStatus::values()) . '.',
            'due_date.date'       => 'The due date must be a valid date.',
            'amount.numeric'      => 'The amount must be a number.',
            'amount.min'          => 'The amount must be at least zero.',
            'billing_period.date' => 'The billing period must be a valid date.',
        ];
    }
}
