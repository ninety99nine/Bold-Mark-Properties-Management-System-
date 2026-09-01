<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'unit_id'    => $this->unit_id,
            'organization_id'  => $this->organization_id,
            'full_name'        => $this->full_name,
            'is_primary'       => (bool) $this->is_primary,
            'user_verified'    => (bool) $this->user_verified,
            'user_display_name' => $this->user_display_name,
            'email'            => $this->email,
            'secondary_emails' => $this->secondary_emails ?? [],
            'phone'            => $this->phone,
            'landline'         => $this->landline,
            'id_number'  => $this->id_number,
            'entity_type'      => $this->entity_type instanceof \BackedEnum ? $this->entity_type->value : $this->entity_type,
            'contact2_name'     => $this->contact2_name,
            'contact2_email'    => $this->contact2_email,
            'contact2_phone'    => $this->contact2_phone,
            'contact2_landline' => $this->contact2_landline,
            'customer_type'     => $this->customer_type,
            'vat_no'            => $this->vat_no,
            'alt_email'         => $this->alt_email,
            'alt_phone'         => $this->alt_phone,
            'payment_type'      => $this->payment_type,
            'pdf_password'      => $this->pdf_password,
            'customer_group'    => $this->customer_group,
            'reference'         => $this->reference,
            'old_customer_code' => $this->old_customer_code,
            'address'    => $this->address,
            'address_line_2'    => $this->address_line_2,
            'suburb'            => $this->suburb,
            'town'              => $this->town,
            'postal_code'       => $this->postal_code,
            'account_holder'    => $this->account_holder,
            'bank_name'         => $this->bank_name,
            'account_type'      => $this->account_type,
            'account_number'    => $this->account_number,
            'branch_code'       => $this->branch_code,
            'branch_name'       => $this->branch_name,
            'notes'             => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            'invoices_count' => $this->whenCounted('invoices'),

            'unit'     => UnitResource::make($this->whenLoaded('unit')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
