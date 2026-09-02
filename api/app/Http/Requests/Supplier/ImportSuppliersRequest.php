<?php

namespace App\Http\Requests\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class ImportSuppliersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Supplier::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'file'         => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'community_id' => ['sometimes', 'nullable', 'uuid', 'exists:communities,id'],
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
            'file.required'       => 'Please select a spreadsheet file to upload.',
            'file.file'           => 'The upload must be a file.',
            'file.mimes'          => 'The file must be an xlsx, xls, or csv spreadsheet.',
            'file.max'            => 'The file may not be larger than 10MB.',
            'community_id.uuid'   => 'The community ID must be a valid UUID.',
            'community_id.exists' => 'The selected community does not exist.',
        ];
    }
}
