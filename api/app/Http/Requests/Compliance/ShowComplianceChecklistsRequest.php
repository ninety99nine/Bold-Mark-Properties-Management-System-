<?php

namespace App\Http\Requests\Compliance;

use App\Models\ComplianceChecklist;
use Illuminate\Foundation\Http\FormRequest;

class ShowComplianceChecklistsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ComplianceChecklist::class);
    }

    public function rules(): array
    {
        return [
            'estate_id'            => ['nullable', 'uuid', 'exists:estates,id'],
            'country'              => ['nullable', 'string', 'max:2'],
            'financial_year_label' => ['nullable', 'string'],
        ];
    }
}
