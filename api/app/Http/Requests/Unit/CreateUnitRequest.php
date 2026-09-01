<?php

namespace App\Http\Requests\Unit;

use App\Enums\OccupancyType;
use App\Enums\OwnerEntityType;
use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Unit::class, $this->route('community')]);
    }

    public function rules(): array
    {
        return [
            'unit_number'           => ['required', 'string', 'max:50'],
            'block_number'          => ['nullable', 'string', 'max:50'],
            'section'               => ['nullable', 'string', 'max:50'],
            'door_number'           => ['nullable', 'string', 'max:50'],
            'address'               => ['nullable', 'string', 'max:500'],
            'pq'                    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'occupancy_type'        => ['required', Rule::in(OccupancyType::values())],
            'status'                => ['sometimes', Rule::in(UnitStatus::values())],
            'levy_override'         => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rent_amount'           => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'billing_pdf'           => ['nullable', 'boolean'],

            // Owner details — always required
            'owner'                 => ['required', 'array'],
            'owner.full_name'       => ['required', 'string', 'max:255'],
            'owner.email'           => ['required', 'email', 'max:255'],
            'owner.phone'           => ['nullable', 'string', 'max:30'],
            'owner.landline'        => ['nullable', 'string', 'max:30'],
            'owner.id_number'       => ['nullable', 'string', 'max:50'],
            'owner.entity_type'     => ['nullable', Rule::in(OwnerEntityType::values())],
            'owner.address'         => ['nullable', 'string', 'max:500'],
            'owner.contact2_name'     => ['nullable', 'string', 'max:255'],
            'owner.contact2_email'    => ['nullable', 'email', 'max:255'],
            'owner.contact2_phone'    => ['nullable', 'string', 'max:30'],
            'owner.contact2_landline' => ['nullable', 'string', 'max:30'],

            // Organization details — required only when occupancy_type is occupant_occupied
            'occupant'                => ['nullable', 'array'],
            'occupant.full_name'      => ['nullable', 'required_if:occupancy_type,occupant_occupied', 'string', 'max:255'],
            'occupant.email'          => ['nullable', 'required_if:occupancy_type,occupant_occupied', 'email', 'max:255'],
            'occupant.phone'          => ['nullable', 'string', 'max:30'],
            'occupant.id_number'      => ['nullable', 'string', 'max:50'],
            'occupant.lease_start'    => ['nullable', 'date'],
            'occupant.lease_end'      => ['nullable', 'date', 'after:occupant.lease_start'],
            'occupant.rent_amount'    => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $community = $this->route('community');
            if (! $community) {
                return;
            }

            $basis = $community->entity_type instanceof \App\Enums\CommunityEntityType
                ? $community->entity_type->billingBasis()
                : null;
            if ($basis === 'levy') {
                if ($this->input('occupancy_type') === 'occupant_occupied') {
                    $v->errors()->add(
                        'occupancy_type',
                        'Units in a levy-billed community cannot be occupant-occupied. Only Owner or Vacant are allowed.'
                    );
                }

                if ($this->has('occupant') && ! empty($this->input('occupant'))) {
                    $v->errors()->add(
                        'occupant',
                        'Occupant details cannot be set on units in a levy-billed community.'
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
            'occupant.full_name.required_if' => 'The occupant\'s full name is required when the unit is occupant-occupied.',
            'occupant.email.required_if'     => 'The occupant\'s email address is required when the unit is occupant-occupied.',
            'occupant.email.email'           => 'The occupant\'s email address must be a valid email.',
            'occupant.lease_end.after'       => 'The lease end date must be after the lease start date.',
        ];
    }
}
