<?php

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'name'   => ['sometimes', 'string', 'max:255'],
            'email'  => ['sometimes', 'email', 'max:255'],
            'phone'  => ['nullable', 'string', 'max:30'],
            'role'   => ['sometimes', 'nullable', 'string', 'exists:roles,name'],
            'status' => ['sometimes', 'nullable', Rule::in(UserStatus::values())],
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
            'name.string'    => 'The full name must be a string.',
            'name.max'       => 'The full name may not exceed 255 characters.',
            'email.email'    => 'The email address must be a valid email.',
            'email.max'      => 'The email address may not exceed 255 characters.',
            'phone.string'   => 'The phone number must be a string.',
            'phone.max'      => 'The phone number may not exceed 30 characters.',
            'role.exists'    => 'The selected role does not exist.',
            'status.in'      => 'The selected status is invalid.',
        ];
    }
}
