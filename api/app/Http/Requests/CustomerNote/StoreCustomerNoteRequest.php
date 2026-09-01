<?php

namespace App\Http\Requests\CustomerNote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomerNoteRequest extends FormRequest
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
            'note'          => ['nullable', 'string', 'max:5000'],
            'documents'     => ['nullable', 'array', 'max:5'],
            'documents.*'   => ['file', 'max:10240'],
            'document_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance — a note requires text or a document.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! filled($this->input('note')) && ! $this->hasFile('documents')) {
                $validator->errors()->add('note', 'Enter a note or attach a document.');
            }
        });
    }
}
