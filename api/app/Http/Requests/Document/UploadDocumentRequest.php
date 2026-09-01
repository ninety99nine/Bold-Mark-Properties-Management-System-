<?php

namespace App\Http\Requests\Document;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'name'     => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'file', 'max:20480'],
        ];
    }
}
