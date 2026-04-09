<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
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
            'user_ids.required'  => 'At least one user ID is required.',
            'user_ids.array'     => 'The user IDs must be provided as an array.',
            'user_ids.min'       => 'At least one user ID must be provided.',
            'user_ids.*.integer' => 'Each user ID must be an integer.',
            'user_ids.*.exists'  => 'One or more of the selected users do not exist.',
        ];
    }
}
