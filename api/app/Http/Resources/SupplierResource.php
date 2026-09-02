<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'supplier_code'       => $this->supplier_code,
            'reference'           => $this->reference,
            'name'                => $this->name,
            'supplier_type'       => $this->supplier_type?->value,
            'status'              => $this->status?->value,
            'payment_type'        => $this->payment_type?->value,
            'email'               => $this->email,
            'alt_email'           => $this->alt_email,
            'phone'               => $this->phone,
            'alt_phone'           => $this->alt_phone,
            'bank_name'           => $this->bank_name,
            'account_type'        => $this->account_type?->value,
            'account_number'      => $this->account_number,
            'branch_code'         => $this->branch_code,
            'branch_name'         => $this->branch_name,
            'vat_number'          => $this->vat_number,
            'registration_number' => $this->registration_number,
            'address'             => $this->address,
            'address_line_1'      => $this->address_line_1,
            'address_line_2'      => $this->address_line_2,
            'suburb'              => $this->suburb,
            'town'                => $this->town,
            'postal_code'         => $this->postal_code,
            'is_active'           => (bool) $this->is_active,
            'balance'             => (float) $this->balance,
            'supplier_group_id'   => $this->supplier_group_id,
            'group_name'          => $this->whenLoaded('group', fn () => $this->group?->name),
            'community_id'        => $this->community_id,
            'label'               => "{$this->supplier_code} - {$this->name}",
            'created_at'          => $this->created_at?->toDateTimeString(),
            'updated_at'          => $this->updated_at?->toDateTimeString(),

            'community'           => CommunityResource::make($this->whenLoaded('community')),
            'group'               => SupplierGroupResource::make($this->whenLoaded('group')),
        ];
    }
}
