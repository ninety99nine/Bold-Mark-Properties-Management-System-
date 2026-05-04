<?php

namespace App\Http\Requests\RiskRule;

use Illuminate\Foundation\Http\FormRequest;

class DeleteRiskRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('riskRule'));
    }

    public function rules(): array
    {
        return [];
    }
}
