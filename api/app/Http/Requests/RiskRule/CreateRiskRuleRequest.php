<?php

namespace App\Http\Requests\RiskRule;

use App\Enums\RiskSeverity;
use App\Models\RiskRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRiskRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RiskRule::class);
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:100'],
            'description'          => ['nullable', 'string', 'max:500'],
            'severity'             => ['required', Rule::in(RiskSeverity::values())],
            'conditions'           => ['required', 'array', 'min:1'],
            'conditions.*.type'    => ['required', 'string', Rule::in(['overdue_amount', 'overdue_invoice_count', 'days_overdue', 'arrears_rate'])],
            'conditions.*.operator' => ['required', 'string', Rule::in(['>='])],
            'conditions.*.value'   => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'              => 'The rule name is required.',
            'name.max'                   => 'The rule name may not exceed 100 characters.',
            'severity.required'          => 'The severity level is required.',
            'severity.in'                => 'The severity must be one of: ' . implode(', ', RiskSeverity::values()) . '.',
            'conditions.required'        => 'At least one condition is required.',
            'conditions.min'             => 'At least one condition is required.',
            'conditions.*.type.required' => 'Each condition must have a type.',
            'conditions.*.type.in'       => 'Invalid condition type.',
            'conditions.*.value.required' => 'Each condition must have a threshold value.',
            'conditions.*.value.min'     => 'Threshold values must be zero or greater.',
        ];
    }
}
