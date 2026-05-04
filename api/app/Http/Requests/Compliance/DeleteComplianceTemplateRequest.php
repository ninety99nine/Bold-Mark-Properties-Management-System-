<?php

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class DeleteComplianceTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('complianceTemplate'));
    }

    public function rules(): array
    {
        return [];
    }
}
