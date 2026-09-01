<?php

namespace App\Http\Requests\Compliance;

use App\Models\ComplianceChecklist;
use Illuminate\Foundation\Http\FormRequest;

class CreateComplianceChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ComplianceChecklist::class);
    }

    public function rules(): array
    {
        return [
            'community_id'            => ['required', 'uuid', 'exists:communities,id'],
            'financial_year_label' => ['required', 'string', 'max:20'],
            'financial_year_start' => ['required', 'date'],
            'financial_year_end'   => ['required', 'date', 'after:financial_year_start'],
            'template_id'          => ['nullable', 'uuid', 'exists:compliance_templates,id'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'community_id.required'            => 'An community must be selected.',
            'community_id.exists'              => 'The selected community does not exist.',
            'financial_year_label.required'  => 'The financial year label is required.',
            'financial_year_start.required'  => 'The financial year start date is required.',
            'financial_year_end.required'    => 'The financial year end date is required.',
            'financial_year_end.after'       => 'The financial year end date must be after the start date.',
            'template_id.exists'            => 'The selected template does not exist.',
        ];
    }
}
