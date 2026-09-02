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
            'community_id'            => ['nullable', 'uuid', 'exists:communities,id'],
            'country'              => ['nullable', 'string', 'max:2'],
            'financial_year_label' => ['nullable', 'string'],
        ];
    }
}
