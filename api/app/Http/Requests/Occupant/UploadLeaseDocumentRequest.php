<?php

namespace App\Http\Requests\Occupant;

use App\Models\Occupant;
use Illuminate\Foundation\Http\FormRequest;

class UploadLeaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Occupant::class, $this->route('unit'), $this->route('occupant')]);
    }

    public function rules(): array
    {
        return [
            'lease_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'lease_document.required' => 'Please select a file to upload.',
            'lease_document.mimes'    => 'The lease document must be a PDF, JPG, or PNG file.',
            'lease_document.max'      => 'The lease document may not be larger than 5 MB.',
        ];
    }
}
