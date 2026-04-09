<?php

namespace App\Http\Requests\CashbookEntry;

use App\Enums\CashbookEntryType;
use App\Models\CashbookEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCashbookEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', CashbookEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estate_id'   => ['required', 'uuid', 'exists:estates,id'],
            'date'        => ['required', 'date'],
            'type'        => ['required', Rule::in(CashbookEntryType::values())],
            'description' => ['required', 'string', 'max:500'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'unit_id'          => ['nullable', 'uuid', 'exists:units,id'],
            'invoice_id'       => ['nullable', 'uuid', 'exists:invoices,id'],
            'proof_of_payment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
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
            'estate_id.required'   => 'The estate is required.',
            'estate_id.uuid'       => 'The estate ID must be a valid UUID.',
            'estate_id.exists'     => 'The selected estate does not exist.',
            'date.required'        => 'The transaction date is required.',
            'date.date'            => 'The transaction date must be a valid date.',
            'type.required'        => 'The entry type is required.',
            'type.in'              => 'The entry type must be one of: ' . implode(', ', CashbookEntryType::values()) . '.',
            'description.required' => 'The description is required.',
            'description.string'   => 'The description must be a string.',
            'description.max'      => 'The description may not exceed 500 characters.',
            'amount.required'      => 'The amount is required.',
            'amount.numeric'       => 'The amount must be a number.',
            'amount.min'           => 'The amount must be greater than zero.',
            'notes.max'            => 'The notes may not exceed 1000 characters.',
            'unit_id.uuid'         => 'The unit ID must be a valid UUID.',
            'unit_id.exists'       => 'The selected unit does not exist.',
            'invoice_id.uuid'              => 'The invoice ID must be a valid UUID.',
            'invoice_id.exists'            => 'The selected invoice does not exist.',
            'proof_of_payment.file'        => 'The proof of payment must be a valid file.',
            'proof_of_payment.mimes'       => 'The proof of payment must be a PDF, JPG, or PNG.',
            'proof_of_payment.max'         => 'The proof of payment may not exceed 10 MB.',
        ];
    }
}
