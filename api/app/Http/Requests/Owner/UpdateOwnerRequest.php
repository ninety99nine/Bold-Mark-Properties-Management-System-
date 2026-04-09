<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('owner'));
    }

    public function rules(): array
    {
        return [
            'full_name'  => ['sometimes', 'string', 'max:255'],
            'email'      => ['sometimes', 'email', 'max:255'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:30'],
            'id_number'  => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'    => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'The email address must be a valid email.',
        ];
    }
}
