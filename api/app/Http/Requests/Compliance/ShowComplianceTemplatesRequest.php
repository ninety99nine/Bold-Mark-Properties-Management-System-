<?php

namespace App\Http\Requests\Compliance;

use App\Models\ComplianceTemplate;
use Illuminate\Foundation\Http\FormRequest;

class ShowComplianceTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ComplianceTemplate::class);
    }

    public function rules(): array
    {
        return [
            'country' => ['nullable', 'string', 'max:2'],
        ];
    }
}
