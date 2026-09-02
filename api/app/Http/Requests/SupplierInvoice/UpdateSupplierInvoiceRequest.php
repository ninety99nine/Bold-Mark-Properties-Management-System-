<?php

namespace App\Http\Requests\SupplierInvoice;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierInvoiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('supplierInvoice'));
    }

    /**
     * Decode the JSON-encoded `items` payload sent alongside multipart file
     * uploads so the array rules validate uniformly.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('items'))) {
            $decoded = json_decode($this->input('items'), true);
            if (is_array($decoded)) {
                $this->merge(['items' => $decoded]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'supplier_id'            => ['sometimes', 'uuid', 'exists:suppliers,id'],
            'status'                 => ['nullable', Rule::in(SupplierInvoiceStatus::values())],
            'type'                   => ['nullable', Rule::in(SupplierInvoiceType::values())],
            'invoice_date'           => ['sometimes', 'date'],
            'due_date'               => ['nullable', 'date'],
            'source_document_number' => ['nullable', 'string', 'max:255'],
            'our_reference'          => ['nullable', 'string', 'max:255'],
            'supplier_reference'     => ['nullable', 'string', 'max:255'],
            'description'            => ['nullable', 'string', 'max:500'],

            'items'                  => ['sometimes', 'array', 'min:1'],
            'items.*.ledger_id'      => ['nullable', 'uuid', 'exists:ledgers,id'],
            'items.*.account_name'   => ['nullable', 'string', 'max:255'],
            'items.*.description'    => ['nullable', 'string', 'max:500'],
            'items.*.quantity'       => ['nullable', 'numeric'],
            'items.*.unit_price'     => ['nullable', 'numeric'],
            'items.*.amount'         => ['nullable', 'numeric'],
            'items.*.discount'       => ['nullable', 'numeric'],
            'items.*.tax_rate'       => ['nullable', 'numeric'],
        ];
    }
}
