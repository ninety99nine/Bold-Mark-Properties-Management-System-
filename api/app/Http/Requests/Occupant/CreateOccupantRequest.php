<?php

namespace App\Http\Requests\Occupant;

use App\Models\Occupant;
use Illuminate\Foundation\Http\FormRequest;

class CreateOccupantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Occupant::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'full_name'          => ['required', 'string', 'max:255'],
            'email'              => ['required', 'email', 'max:255'],
            'secondary_emails'   => ['sometimes', 'nullable', 'array'],
            'secondary_emails.*' => ['email', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'id_number'          => ['nullable', 'string', 'max:50'],
            'postal_address'     => ['nullable', 'string', 'max:255'],
            'car_registration'   => ['nullable', 'string', 'max:50'],
            'lease_start'        => ['nullable', 'date'],
            'lease_end'          => ['nullable', 'date', 'after:lease_start'],
            'rent_amount'        => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'       => 'The occupant\'s full name is required.',
            'email.required'           => 'The occupant\'s email address is required.',
            'email.email'              => 'The occupant\'s email address must be a valid email.',
            'secondary_emails.*.email' => 'Each additional email must be a valid email address.',
            'lease_end.after'          => 'The lease end date must be after the lease start date.',
        ];
    }
}
