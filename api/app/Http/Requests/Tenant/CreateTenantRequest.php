<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Tenant::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'full_name'   => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'id_number'   => ['nullable', 'string', 'max:50'],
            'lease_start' => ['nullable', 'date'],
            'lease_end'   => ['nullable', 'date', 'after:lease_start'],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'  => 'The tenant\'s full name is required.',
            'email.required'      => 'The tenant\'s email address is required.',
            'email.email'         => 'The tenant\'s email address must be a valid email.',
            'lease_end.after'     => 'The lease end date must be after the lease start date.',
        ];
    }
}
