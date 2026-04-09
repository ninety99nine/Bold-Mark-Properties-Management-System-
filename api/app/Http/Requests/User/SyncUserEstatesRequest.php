<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserEstatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estate_ids'   => ['present', 'array'],
            'estate_ids.*' => ['uuid', 'exists:estates,id'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estate_ids.present'   => 'The estate_ids field is required.',
            'estate_ids.array'     => 'The estate_ids must be an array.',
            'estate_ids.*.uuid'    => 'Each estate ID must be a valid UUID.',
            'estate_ids.*.exists'  => 'One or more selected estates do not exist.',
        ];
    }
}
