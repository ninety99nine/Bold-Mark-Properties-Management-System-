<?php

namespace App\Http\Requests\CreditNote;

use App\Models\CreditNote;
use Illuminate\Foundation\Http\FormRequest;

class CreateCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CreditNote::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['email_credit_note', 'attach_statement'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'unit_id'            => ['required', 'uuid', 'exists:units,id'],
            'credit_note_date'   => ['required', 'date'],
            'reason'             => ['nullable', 'string', 'max:1000'],
            'order_no'           => ['nullable', 'string', 'max:100'],
            'reference'          => ['nullable', 'string', 'max:100'],
            'applied_invoice_id' => ['nullable', 'uuid', 'exists:invoices,id'],

            'items'               => ['required', 'array', 'min:1'],
            'items.*.ledger_id'   => ['required', 'uuid', 'exists:ledgers,id'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0', 'max:999999'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.amount'      => ['required', 'numeric', 'min:0', 'max:9999999999.99'],

            'email_credit_note'  => ['sometimes', 'boolean'],
            'attach_statement'   => ['sometimes', 'boolean'],
            'email_recipients'   => ['nullable', 'array'],
            'email_recipients.*' => ['email'],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_id.required'           => 'A customer (unit) is required.',
            'unit_id.exists'             => 'The selected customer does not exist.',
            'credit_note_date.required'  => 'The credit note date is required.',
            'applied_invoice_id.exists'  => 'The selected invoice does not exist.',
            'items.required'             => 'At least one line item is required.',
            'items.min'                  => 'At least one line item is required.',
            'items.*.ledger_id.required' => 'Each line item needs an account.',
            'items.*.ledger_id.exists'   => 'The selected account does not exist.',
            'items.*.quantity.required'  => 'Each line item needs a quantity.',
            'items.*.amount.required'    => 'Each line item needs a unit price.',
        ];
    }
}
