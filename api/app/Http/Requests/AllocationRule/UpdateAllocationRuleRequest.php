<?php

namespace App\Http\Requests\AllocationRule;

use App\Enums\JournalLineType;
use App\Enums\VatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAllocationRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('allocationRule'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description_starts_with' => ['nullable', 'string', 'max:255'],
            'description_contains'    => ['nullable', 'string', 'max:255'],
            'ledger_type'             => ['required', 'string', Rule::in(JournalLineType::values())],
            'ledger_id'               => ['nullable', 'uuid', 'exists:ledgers,id'],
            'unit_id'                 => ['nullable', 'uuid', 'exists:units,id'],
            'supplier_id'             => ['nullable', 'uuid', 'exists:suppliers,id'],
            'vat_type'                => ['nullable', 'string', Rule::in(VatType::values())],
            'remarks'                 => ['nullable', 'string', 'max:500'],
            'apply_to_positive'       => ['nullable', 'boolean'],
            'apply_to_negative'       => ['nullable', 'boolean'],
            'bank_account_ids'        => ['nullable', 'array'],
            'bank_account_ids.*'      => ['uuid', 'exists:bank_accounts,id'],
            'sort_order'              => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Enforce WeConnectU's rules: at least one text criterion, and the correct
     * target account for the chosen ledger type.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $startsWith = trim((string) $this->input('description_starts_with'));
            $contains   = trim((string) $this->input('description_contains'));

            if ($startsWith === '' && $contains === '') {
                $validator->errors()->add('description_starts_with', 'Enter a "starts with" or "contains" value.');
            }

            $type = $this->input('ledger_type');

            if (in_array($type, [JournalLineType::GENERAL->value, JournalLineType::RESERVE_FUND->value], true)
                && empty($this->input('ledger_id'))) {
                $validator->errors()->add('ledger_id', 'Select an account for this ledger type.');
            }

            if ($type === JournalLineType::CUSTOMER->value && empty($this->input('unit_id'))) {
                $validator->errors()->add('unit_id', 'Select a customer for this ledger type.');
            }

            if ($type === JournalLineType::SUPPLIER->value && empty($this->input('supplier_id'))) {
                $validator->errors()->add('supplier_id', 'Select a supplier for this ledger type.');
            }
        });
    }
}
