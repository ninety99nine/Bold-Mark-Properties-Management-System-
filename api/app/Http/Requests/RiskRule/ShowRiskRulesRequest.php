<?php

namespace App\Http\Requests\RiskRule;

use App\Models\RiskRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowRiskRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RiskRule::class);
    }

    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'in:true,false,1,0'],
        ];
    }
}
