<?php

namespace App\Http\Requests\Compliance;

use App\Enums\ComplianceItemPriority;
use App\Enums\ComplianceItemStatus;
use App\Models\ComplianceChecklistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplianceChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complianceChecklistItem'));
    }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'string', 'max:255'],
            'category'         => ['sometimes', 'string', 'max:50'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'priority'         => ['sometimes', Rule::in(ComplianceItemPriority::values())],
            'status'           => ['sometimes', Rule::in(ComplianceItemStatus::values())],
            'due_date'         => ['nullable', 'date'],
            'assigned_to_id'   => ['nullable', 'integer', 'exists:users,id'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'is_recurring'     => ['nullable', 'boolean'],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'priority.in' => 'The priority must be one of: ' . implode(', ', ComplianceItemPriority::values()) . '.',
            'status.in'   => 'The status must be one of: ' . implode(', ', ComplianceItemStatus::values()) . '.',
        ];
    }
}
