<?php

namespace App\Http\Requests\Compliance;

use App\Enums\ComplianceItemPriority;
use App\Models\ComplianceChecklistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateComplianceChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ComplianceChecklistItem::class);
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'category'       => ['required', 'string', 'max:50'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'priority'       => ['required', Rule::in(ComplianceItemPriority::values())],
            'due_date'       => ['nullable', 'date'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_recurring'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'The item name is required.',
            'name.max'          => 'The item name may not exceed 255 characters.',
            'category.required' => 'The item category is required.',
            'priority.required' => 'The item priority is required.',
            'priority.in'       => 'The priority must be one of: ' . implode(', ', ComplianceItemPriority::values()) . '.',
        ];
    }
}
