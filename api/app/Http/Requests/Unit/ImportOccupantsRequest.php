<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class ImportOccupantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Unit::class, $this->route('community')]);
    }

    public function rules(): array
    {
        return [
            'rows'                        => ['required', 'array', 'min:1'],
            'rows.*.unit_number'          => ['sometimes', 'nullable', 'string'],
            'rows.*.customer_code'        => ['sometimes', 'nullable', 'string'],
            'rows.*.occupant_full_name'   => ['sometimes', 'nullable', 'string'],
            'rows.*.occupant_email'       => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required' => 'No rows were provided for import.',
            'rows.min'      => 'At least one row is required.',
        ];
    }
}
