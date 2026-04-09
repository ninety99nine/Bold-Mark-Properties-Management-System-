<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class InviteUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role'  => ['required', 'string', 'exists:roles,name'],
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
            'name.required'  => 'The full name is required.',
            'name.string'    => 'The full name must be a string.',
            'name.max'       => 'The full name may not exceed 255 characters.',
            'email.required' => 'The email address is required.',
            'email.email'    => 'The email address must be a valid email.',
            'email.max'      => 'The email address may not exceed 255 characters.',
            'email.unique'   => 'A user with this email address already exists.',
            'phone.max'      => 'The phone number may not exceed 30 characters.',
            'role.required'  => 'A role must be assigned to the user.',
            'role.string'    => 'The role must be a string.',
            'role.exists'    => 'The selected role does not exist.',
        ];
    }
}
