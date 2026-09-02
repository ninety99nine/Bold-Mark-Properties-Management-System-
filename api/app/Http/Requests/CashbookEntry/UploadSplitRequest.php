<?php

namespace App\Http\Requests\CashbookEntry;

use Illuminate\Foundation\Http\FormRequest;

class UploadSplitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('allocate', $this->route('cashbookEntry'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A spreadsheet file is required.',
            'file.file'     => 'The upload must be a file.',
            'file.mimes'    => 'The file must be an xlsx, xls or csv spreadsheet.',
        ];
    }
}
