<?php

namespace App\Http\Requests\Unit;

use App\Enums\OccupancyType;
use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Unit::class, $this->route('estate')]);
    }

    public function rules(): array
    {
        return [
            'unit_number'           => ['required', 'string', 'max:50'],
            'address'               => ['nullable', 'string', 'max:500'],
            'occupancy_type'        => ['required', Rule::in(OccupancyType::values())],
            'status'                => ['sometimes', Rule::in(UnitStatus::values())],
            'levy_override'         => ['nullable', 'numeric', 'min:0'],
            'rent_amount'           => ['nullable', 'numeric', 'min:0'],

            // Owner details — always required
            'owner'                 => ['required', 'array'],
            'owner.full_name'       => ['required', 'string', 'max:255'],
            'owner.email'           => ['required', 'email', 'max:255'],
            'owner.phone'           => ['nullable', 'string', 'max:30'],
            'owner.id_number'       => ['nullable', 'string', 'max:50'],
            'owner.address'         => ['nullable', 'string', 'max:500'],

            // Tenant details — required only when occupancy_type is tenant_occupied
            'tenant'                => ['nullable', 'array'],
            'tenant.full_name'      => ['nullable', 'required_if:occupancy_type,tenant_occupied', 'string', 'max:255'],
            'tenant.email'          => ['nullable', 'required_if:occupancy_type,tenant_occupied', 'email', 'max:255'],
            'tenant.phone'          => ['nullable', 'string', 'max:30'],
            'tenant.id_number'      => ['nullable', 'string', 'max:50'],
            'tenant.lease_start'    => ['nullable', 'date'],
            'tenant.lease_end'      => ['nullable', 'date', 'after:tenant.lease_start'],
            'tenant.rent_amount'    => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $estate = $this->route('estate');
            if (! $estate) {
                return;
            }

            if ($estate->type === 'sectional_title') {
                if ($this->input('occupancy_type') === 'tenant_occupied') {
                    $v->errors()->add(
                        'occupancy_type',
                        'Units in a Sectional Title estate cannot be tenant-occupied. Only Owner or Vacant are allowed.'
                    );
                }

                if ($this->has('tenant') && ! empty($this->input('tenant'))) {
                    $v->errors()->add(
                        'tenant',
                        'Tenant details cannot be set on units in a Sectional Title estate.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'unit_number.required'         => 'The unit number is required.',
            'occupancy_type.required'      => 'The occupancy type is required.',
            'occupancy_type.in'            => 'The occupancy type must be one of: ' . implode(', ', OccupancyType::values()) . '.',
            'status.in'                    => 'The status must be one of: ' . implode(', ', UnitStatus::values()) . '.',
            'owner.required'               => 'Owner details are required.',
            'owner.full_name.required'     => 'The owner\'s full name is required.',
            'owner.email.required'         => 'The owner\'s email address is required.',
            'owner.email.email'            => 'The owner\'s email address must be a valid email.',
            'tenant.full_name.required_if' => 'The tenant\'s full name is required when the unit is tenant-occupied.',
            'tenant.email.required_if'     => 'The tenant\'s email address is required when the unit is tenant-occupied.',
            'tenant.email.email'           => 'The tenant\'s email address must be a valid email.',
            'tenant.lease_end.after'       => 'The lease end date must be after the lease start date.',
        ];
    }
}
