<?php

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class DeleteComplianceChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('complianceChecklist'));
    }

    public function rules(): array
    {
        return [];
    }
}
