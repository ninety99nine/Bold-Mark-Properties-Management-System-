<?php

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplianceChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complianceChecklist'));
    }

    public function rules(): array
    {
        return [
            'financial_year_label' => ['sometimes', 'string', 'max:20'],
            'financial_year_start' => ['sometimes', 'date'],
            'financial_year_end'   => ['sometimes', 'date'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'financial_year_label.max' => 'The financial year label may not exceed 20 characters.',
        ];
    }
}
