<?php

namespace App\Http\Requests\UnitTenant;

use App\Models\UnitTenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [UnitTenant::class, $this->route('unit'), $this->route('unitTenant')]);
    }

    public function rules(): array
    {
        return [
            'full_name'        => ['sometimes', 'string', 'max:255'],
            'email'            => ['sometimes', 'email', 'max:255'],
            'phone'            => ['sometimes', 'nullable', 'string', 'max:30'],
            'id_number'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'lease_start'      => ['sometimes', 'nullable', 'date'],
            'lease_end'        => ['sometimes', 'nullable', 'date', 'after:lease_start'],
            'rent_amount'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'move_out_date'    => ['sometimes', 'nullable', 'date'],
            'move_out_reason'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'move_out_notes'   => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'        => 'The tenant\'s email address must be a valid email.',
            'lease_end.after'    => 'The lease end date must be after the lease start date.',
        ];
    }
}
