<?php

namespace App\Http\Requests\RiskRule;

use App\Enums\RiskSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRiskRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('riskRule'));
    }

    public function rules(): array
    {
        return [
            'name'                 => ['sometimes', 'string', 'max:100'],
            'description'          => ['sometimes', 'nullable', 'string', 'max:500'],
            'severity'             => ['sometimes', Rule::in(RiskSeverity::values())],
            'is_active'            => ['sometimes', 'boolean'],
            'conditions'           => ['sometimes', 'array', 'min:1'],
            'conditions.*.type'    => ['required_with:conditions', 'string', Rule::in(['overdue_amount', 'overdue_invoice_count', 'days_overdue', 'arrears_rate'])],
            'conditions.*.operator' => ['required_with:conditions', 'string', Rule::in(['>='])],
            'conditions.*.value'   => ['required_with:conditions', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'severity.in' => 'The severity must be one of: ' . implode(', ', RiskSeverity::values()) . '.',
        ];
    }
}
