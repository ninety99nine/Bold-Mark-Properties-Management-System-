<?php

namespace App\Http\Requests\Compliance;

use App\Enums\ComplianceItemPriority;
use App\Models\ComplianceTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateComplianceTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ComplianceTemplate::class);
    }

    public function rules(): array
    {
        return [
            'name'                       => ['required', 'string', 'max:255'],
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
            'name.required'              => 'The template name is required.',
            'name.max'                   => 'The template name may not exceed 255 characters.',
            'items.*.name.required_with' => 'Each item must have a name.',
            'items.*.category.required_with' => 'Each item must have a category.',
            'items.*.priority.required_with' => 'Each item must have a priority.',
            'items.*.priority.in'        => 'Each item priority must be one of: ' . implode(', ', ComplianceItemPriority::values()) . '.',
        ];
    }
}
