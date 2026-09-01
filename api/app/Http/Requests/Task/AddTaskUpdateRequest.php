<?php

namespace App\Http\Requests\Task;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class AddTaskUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'feedback'      => ['required_without:attachments', 'nullable', 'string', 'max:10000'],
            'notify'        => ['nullable', 'string', 'max:255'],
            'attachments'   => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
