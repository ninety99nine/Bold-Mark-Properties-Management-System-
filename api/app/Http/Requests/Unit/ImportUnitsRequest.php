<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class ImportUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Unit::class, $this->route('community')]);
    }

    public function rules(): array
    {
        // Row keys may use either the import-template system fields or the WeConnectU
        // export headers ("Unit No", "Owner / Contact Name", …). The service normalizes
        // and validates each row and reports per-row errors, so the request only guards
        // the envelope shape here.
        return [
            'rows' => ['required', 'array', 'min:1'],
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
