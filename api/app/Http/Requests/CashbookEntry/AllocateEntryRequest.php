<?php

namespace App\Http\Requests\CashbookEntry;

use App\Enums\JournalLineType;
use App\Enums\VatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('allocate', $this->route('cashbookEntry'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'ledger_type' => ['required', Rule::in(JournalLineType::values())],
            'ledger_id'   => ['nullable', 'uuid', 'required_if:ledger_type,general,reserve_fund'],
            'unit_id'     => ['nullable', 'uuid', 'required_if:ledger_type,customer'],
            'supplier_id' => ['nullable', 'uuid', 'required_if:ledger_type,supplier'],
            'vat_type'    => ['nullable', Rule::in(VatType::values())],
            'remarks'     => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'ledger_type.required'  => 'The ledger type is required for allocation.',
            'ledger_type.in'        => 'The ledger type is invalid.',
            'ledger_id.required_if' => 'The account is required for this ledger type.',
            'ledger_id.uuid'        => 'The account ID must be a valid UUID.',
            'unit_id.required_if'   => 'The customer is required for a customer allocation.',
            'unit_id.uuid'          => 'The customer ID must be a valid UUID.',
            'supplier_id.required_if' => 'The supplier is required for a supplier allocation.',
            'supplier_id.uuid'      => 'The supplier ID must be a valid UUID.',
            'vat_type.in'           => 'The VAT type is invalid.',
            'remarks.max'           => 'The remarks may not exceed 500 characters.',
        ];
    }
}
