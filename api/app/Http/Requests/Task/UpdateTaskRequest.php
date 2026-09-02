<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskStatus;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:10000'],
            'category'         => ['nullable', 'string', 'max:255'],
            'task_type'        => ['nullable', 'string', 'max:255'],
            'area'             => ['nullable', 'string', 'max:255'],
            'recurring_type'   => ['nullable', 'string', 'max:255'],
            'assignee_name'    => ['nullable', 'string', 'max:255'],
            'assignee_user_id' => ['nullable', 'string', 'max:36'],
            'status'           => ['nullable', Rule::in(TaskStatus::values())],
            'internal'         => ['nullable', 'boolean'],
            'due_date'         => ['nullable', 'date'],
            'contacts'         => ['nullable', 'array', 'max:50'],
            'contacts.*'       => ['nullable', 'string', 'max:255'],
            'supplier_names'   => ['nullable', 'array', 'max:50'],
            'supplier_names.*' => ['nullable', 'string', 'max:255'],
            'attachments'      => ['nullable', 'array', 'max:10'],
            'attachments.*'    => ['file', 'max:10240'],
        ];
    }
}
