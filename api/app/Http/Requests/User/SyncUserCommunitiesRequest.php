<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserCommunitiesRequest extends FormRequest
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
            'community_ids'   => ['present', 'array'],
            'community_ids.*' => ['uuid', 'exists:communities,id'],
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
            'community_ids.present'   => 'The community_ids field is required.',
            'community_ids.array'     => 'The community_ids must be an array.',
            'community_ids.*.uuid'    => 'Each community ID must be a valid UUID.',
            'community_ids.*.exists'  => 'One or more selected communities do not exist.',
        ];
    }
}
