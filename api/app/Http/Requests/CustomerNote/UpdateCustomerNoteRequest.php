<?php

namespace App\Http\Requests\CustomerNote;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('applyCustomerStatuses', $this->route('community'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'note'                    => ['nullable', 'string', 'max:5000'],
            'documents'               => ['nullable', 'array', 'max:5'],
            'documents.*'             => ['file', 'max:10240'],
            'document_name'           => ['nullable', 'string', 'max:255'],
            'remove_attachment_ids'   => ['nullable', 'array'],
            'remove_attachment_ids.*' => ['uuid'],
        ];
    }
}
