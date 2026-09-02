<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    /**
     * Normalise checkbox-style booleans that arrive as "true"/"1" strings from
     * a multipart form before validation runs.
     */
    protected function prepareForValidation(): void
    {
        foreach (['email_invoice', 'attach_statement'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_id'            => ['required', 'uuid', 'exists:units,id'],
            'bank_account_id'    => ['nullable', 'uuid', 'exists:bank_accounts,id'],
            'invoice_date'       => ['required', 'date'],
            'due_date'           => ['required', 'date', 'after_or_equal:invoice_date'],

            'items'                 => ['required', 'array', 'min:1'],
            'items.*.ledger_id'     => ['required', 'uuid', 'exists:ledgers,id'],
            'items.*.description'   => ['nullable', 'string', 'max:1000'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0', 'max:999999'],
            'items.*.tax_rate'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.amount'        => ['required', 'numeric', 'min:0', 'max:9999999999.99'],

            'attachment'         => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],

            'email_invoice'      => ['sometimes', 'boolean'],
            'attach_statement'   => ['sometimes', 'boolean'],
            'email_recipients'   => ['nullable', 'array'],
            'email_recipients.*' => ['email'],
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
            'unit_id.required'          => 'A customer (unit) is required.',
            'unit_id.exists'            => 'The selected customer does not exist.',
            'bank_account_id.exists'    => 'The selected bank account does not exist.',
            'invoice_date.required'     => 'The invoice date is required.',
            'due_date.required'         => 'The due date is required.',
            'due_date.after_or_equal'   => 'The due date must be on or after the invoice date.',
            'items.required'            => 'At least one line item is required.',
            'items.min'                 => 'At least one line item is required.',
            'items.*.ledger_id.required' => 'Each line item needs an account.',
            'items.*.ledger_id.exists'   => 'The selected account does not exist.',
            'items.*.quantity.required'  => 'Each line item needs a quantity.',
            'items.*.amount.required'    => 'Each line item needs an amount.',
            'attachment.mimes'           => 'The attachment must be a PDF, image, Word, or Excel file.',
            'attachment.max'             => 'The attachment may not be larger than 10 MB.',
        ];
    }
}
