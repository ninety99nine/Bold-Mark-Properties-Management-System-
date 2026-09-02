<?php

namespace App\Http\Requests\Ledger;

use App\Models\Ledger;
use App\Enums\VatType;
use App\Enums\LedgerAppliesTo;
use App\Enums\FinancialCategory;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Ledger::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Two shapes are supported: the legacy Ledger create (no `type` field,
     * uses applies_to / is_recurring) and the WeConnectU General Ledger create
     * (`type` = main | sub, with GL classification fields).
     *
     * @return array
     */
    public function rules(): array
    {
        // WeConnectU General Ledger create.
        if ($this->filled('type')) {
            return [
                'type'               => ['required', Rule::in(['main', 'sub'])],
                'name'               => ['required', 'string', 'max:255'],
                'description'        => ['nullable', 'string', 'max:500'],

                // Main-account only: a 4-digit code prefix → "{prefix}/000".
                'prefix'             => ['required_if:type,main', 'nullable', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
                'allow_sub_accounts' => ['nullable', 'boolean'],
                'fund'               => ['nullable', Rule::in(['main', 'reserve'])],

                // Sub-account only: parent main account.
                'parent_id'          => ['required_if:type,sub', 'nullable', 'string', 'exists:ledgers,id'],

                'account_type'       => ['nullable', Rule::in(['income_statement', 'balance_sheet'])],
                'financial_category' => ['nullable', Rule::in(FinancialCategory::values())],
                'tax_type'           => ['nullable', Rule::in(VatType::values())],
                'sort_order'         => ['nullable', 'integer', 'min:1'],
            ];
        }

        // Legacy Ledger create.
        return [
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:500'],
            'applies_to'   => ['required', Rule::in(LedgerAppliesTo::values())],
            'is_recurring' => ['required', 'boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:1'],
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
            'name.required'         => 'The ledger name is required.',
            'type.in'               => 'The account type must be main or sub.',
            'prefix.required_if'    => 'The account number prefix is required for a Main Account.',
            'parent_id.required_if' => 'A Main Account must be selected for a Sub-Account.',
            'applies_to.required'   => 'The applies_to field is required.',
            'applies_to.in'         => 'The applies_to must be one of: ' . implode(', ', LedgerAppliesTo::values()) . '.',
            'is_recurring.required' => 'The is_recurring field is required.',
        ];
    }
}
