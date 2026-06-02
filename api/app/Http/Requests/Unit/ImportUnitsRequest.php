<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class ImportUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Unit::class, $this->route('estate')]);
    }

    public function rules(): array
    {
        return [
            'rows'                      => ['required', 'array', 'min:1'],
            'rows.*.unit_number'        => ['required', 'string'],
            'rows.*.section'            => ['sometimes', 'nullable', 'string'],
            'rows.*.address'            => ['sometimes', 'nullable', 'string'],
            'rows.*.occupancy_type'     => ['sometimes', 'nullable', 'string', 'in:owner_occupied,tenant_occupied,vacant'],
            'rows.*.owner_full_name'    => ['required', 'string'],
            'rows.*.owner_email'        => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required'                   => 'No rows were provided for import.',
            'rows.min'                        => 'At least one row is required.',
            'rows.*.unit_number.required'     => 'Unit number is required.',
            'rows.*.occupancy_type.in'        => 'Occupancy type must be owner_occupied, tenant_occupied, or vacant.',
            'rows.*.owner_full_name.required' => 'Owner full name is required.',
        ];
    }
}
