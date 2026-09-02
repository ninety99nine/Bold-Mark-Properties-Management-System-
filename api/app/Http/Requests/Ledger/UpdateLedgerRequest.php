<?php

namespace App\Http\Requests\Ledger;

use App\Enums\VatType;
use App\Enums\LedgerAppliesTo;
use App\Enums\FinancialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ledger'));
    }

    public function rules(): array
    {
        return [
            'name'               => ['sometimes', 'string', 'max:255'],
            'description'        => ['sometimes', 'nullable', 'string', 'max:500'],
            'applies_to'         => ['sometimes', Rule::in(LedgerAppliesTo::values())],
            'is_recurring'       => ['sometimes', 'boolean'],
            'is_active'          => ['sometimes', 'boolean'],
            'sort_order'         => ['sometimes', 'nullable', 'integer', 'min:1'],

            // WeConnectU General Ledger classification.
            'account_type'       => ['sometimes', Rule::in(['income_statement', 'balance_sheet'])],
            'financial_category' => ['sometimes', 'nullable', Rule::in(FinancialCategory::values())],
            'tax_type'           => ['sometimes', 'nullable', Rule::in(VatType::values())],
            'allow_sub_accounts' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'applies_to.in'  => 'The applies_to must be one of: ' . implode(', ', LedgerAppliesTo::values()) . '.',
        ];
    }
}
