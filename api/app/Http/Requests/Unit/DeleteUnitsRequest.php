<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', [Unit::class, $this->route('estate')]);
    }

    public function rules(): array
    {
        return [
            'unit_ids'   => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_ids.required' => 'At least one unit ID is required.',
            'unit_ids.min'      => 'At least one unit ID must be provided.',
            'unit_ids.*.uuid'   => 'Each unit ID must be a valid UUID.',
        ];
    }
}
