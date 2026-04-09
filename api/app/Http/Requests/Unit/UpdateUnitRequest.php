<?php

namespace App\Http\Requests\Unit;

use App\Enums\OccupancyType;
use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('estate'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'unit_number'           => ['sometimes', 'string', 'max:50'],
            'address'               => ['sometimes', 'nullable', 'string', 'max:500'],
            'occupancy_type'        => ['sometimes', Rule::in(OccupancyType::values())],
            'status'                => ['sometimes', Rule::in(UnitStatus::values())],
            'levy_override'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rent_amount'           => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Owner details
            'owner'                 => ['sometimes', 'array'],
            'owner.full_name'       => ['sometimes', 'string', 'max:255'],
            'owner.email'           => ['sometimes', 'email', 'max:255'],
            'owner.phone'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'owner.id_number'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.address'         => ['sometimes', 'nullable', 'string', 'max:500'],

            // Tenant details
            'tenant'                => ['sometimes', 'nullable', 'array'],
            'tenant.full_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'tenant.email'          => ['sometimes', 'nullable', 'email', 'max:255'],
            'tenant.phone'          => ['sometimes', 'nullable', 'string', 'max:30'],
            'tenant.id_number'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'tenant.lease_start'    => ['sometimes', 'nullable', 'date'],
            'tenant.lease_end'      => ['sometimes', 'nullable', 'date', 'after:tenant.lease_start'],
            'tenant.rent_amount'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
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
            'occupancy_type.in'  => 'The occupancy type must be one of: ' . implode(', ', OccupancyType::values()) . '.',
            'status.in'          => 'The status must be one of: ' . implode(', ', UnitStatus::values()) . '.',
            'owner.email.email'  => 'The owner\'s email address must be a valid email.',
            'tenant.email.email' => 'The tenant\'s email address must be a valid email.',
            'tenant.lease_end.after' => 'The lease end date must be after the lease start date.',
        ];
    }
}
