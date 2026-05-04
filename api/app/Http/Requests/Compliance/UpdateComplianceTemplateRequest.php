<?php

namespace App\Http\Requests\Compliance;

use App\Enums\ComplianceItemPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplianceTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complianceTemplate'));
    }

    public function rules(): array
    {
        return [
            'name'                       => ['sometimes', 'string', 'max:255'],
            'description'                => ['nullable', 'string', 'max:2000'],
            'country'                    => ['nullable', 'string', 'max:2'],
            'is_default'                 => ['nullable', 'boolean'],
            'items'                      => ['nullable', 'array'],
            'items.*.name'               => ['required_with:items', 'string', 'max:255'],
            'items.*.category'           => ['required_with:items', 'string', 'max:50'],
            'items.*.description'        => ['nullable', 'string', 'max:2000'],
            'items.*.priority'           => ['required_with:items', Rule::in(ComplianceItemPriority::values())],
            'items.*.default_month_due'  => ['nullable', 'integer', 'min:1', 'max:12'],
            'items.*.sort_order'         => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.name.required_with'     => 'Each item must have a name.',
            'items.*.category.required_with' => 'Each item must have a category.',
            'items.*.priority.required_with' => 'Each item must have a priority.',
            'items.*.priority.in'            => 'Each item priority must be one of: ' . implode(', ', ComplianceItemPriority::values()) . '.',
        ];
    }
}
