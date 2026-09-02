<?php

namespace App\Http\Requests\Occupant;

use App\Models\Occupant;
use Illuminate\Foundation\Http\FormRequest;

class DeleteOccupantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', [Occupant::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'occupant_ids'   => ['required', 'array', 'min:1'],
            'occupant_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'occupant_ids.required' => 'At least one unit occupant ID is required.',
            'occupant_ids.min'      => 'At least one unit occupant ID must be provided.',
            'occupant_ids.*.uuid'   => 'Each unit occupant ID must be a valid UUID.',
        ];
    }
}
