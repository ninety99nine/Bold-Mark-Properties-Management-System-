<?php

namespace App\Http\Requests\Unit;

use App\Enums\CollectionStatus;
use App\Enums\OccupancyType;
use App\Enums\OwnerEntityType;
use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'unit_number'           => ['sometimes', 'string', 'max:50'],
            'block_number'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'section'               => ['sometimes', 'nullable', 'string', 'max:50'],
            'door_number'           => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'               => ['sometimes', 'nullable', 'string', 'max:500'],
            'rental_agent_email'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'attorney_email'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'bondholder_email'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit_notes'            => ['sometimes', 'nullable', 'string', 'max:5000'],
            'pq'                    => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'occupancy_type'        => ['sometimes', Rule::in(OccupancyType::values())],
            'status'                => ['sometimes', Rule::in(UnitStatus::values())],
            'levy_override'         => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rent_amount'           => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'billing_pdf'           => ['sometimes', 'boolean'],
            'collection_status'     => ['sometimes', 'nullable', Rule::in(CollectionStatus::values())],

            // When true, regenerate the customer_code from the (new) owner surname.
            'ownership_change'      => ['sometimes', 'boolean'],

            // Owner details
            'owner'                 => ['sometimes', 'array'],
            'owner.full_name'       => ['sometimes', 'string', 'max:255'],
            'owner.email'           => ['sometimes', 'email', 'max:255'],
            'owner.phone'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'owner.landline'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'owner.id_number'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.entity_type'     => ['sometimes', 'nullable', Rule::in(OwnerEntityType::values())],
            'owner.address'         => ['sometimes', 'nullable', 'string', 'max:500'],
            'owner.contact2_name'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.contact2_email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'owner.contact2_phone'    => ['sometimes', 'nullable', 'string', 'max:30'],
            'owner.contact2_landline' => ['sometimes', 'nullable', 'string', 'max:30'],
            // Customer profile (Edit Customer modal)
            'owner.customer_type'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'owner.vat_no'            => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.alt_email'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.alt_phone'         => ['sometimes', 'nullable', 'string', 'max:30'],
            'owner.payment_type'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.pdf_password'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.customer_group'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.reference'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.old_customer_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.address_line_2'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.suburb'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.town'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.postal_code'       => ['sometimes', 'nullable', 'string', 'max:20'],
            'owner.account_holder'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.bank_name'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.account_type'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.account_number'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.branch_code'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'owner.branch_name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner.notes'             => ['sometimes', 'nullable', 'string', 'max:5000'],

            // Organization details
            'occupant'                => ['sometimes', 'nullable', 'array'],
            'occupant.full_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'occupant.email'          => ['sometimes', 'nullable', 'email', 'max:255'],
            'occupant.phone'          => ['sometimes', 'nullable', 'string', 'max:30'],
            'occupant.id_number'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'occupant.lease_start'    => ['sometimes', 'nullable', 'date'],
            'occupant.lease_end'      => ['sometimes', 'nullable', 'date', 'after:occupant.lease_start'],
            'occupant.rent_amount'    => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
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
            'occupancy_type.in'  => 'The occupancy type must be one of: ' . implode(', ', OccupancyType::values()) . '.',
            'status.in'          => 'The status must be one of: ' . implode(', ', UnitStatus::values()) . '.',
            'owner.email.email'  => 'The owner\'s email address must be a valid email.',
            'occupant.email.email' => 'The occupant\'s email address must be a valid email.',
            'occupant.lease_end.after' => 'The lease end date must be after the lease start date.',
        ];
    }
}
