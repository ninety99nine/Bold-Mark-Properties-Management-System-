<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class BulkImportUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Unit::class, $this->route('estate')]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required for bulk import.',
            'file.file'     => 'The uploaded item must be a file.',
            'file.mimes'    => 'The file must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.max'      => 'The file may not exceed 5MB.',
        ];
    }
}
