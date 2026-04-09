<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitTenantResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'unit_id'     => $this->unit_id,
            'tenant_id'   => $this->tenant_id,
            'full_name'   => $this->full_name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'id_number'   => $this->id_number,
            'is_active'             => (bool) $this->is_active,
            'lease_start'           => $this->lease_start?->toDateString(),
            'lease_end'             => $this->lease_end?->toDateString(),
            'lease_document_url'    => $this->lease_document_url,
            'lease_document_name'   => $this->lease_document_name,
            'move_out_date'         => $this->move_out_date?->toDateString(),
            'move_out_reason'       => $this->move_out_reason,
            'move_out_notes'        => $this->move_out_notes,
            'created_at'            => $this->created_at?->toDateTimeString(),
            'updated_at'  => $this->updated_at?->toDateTimeString(),

            'rent_amount' => $this->when($this->relationLoaded('unit'), fn() => $this->unit?->rent_amount),

            'invoices_count' => $this->whenCounted('invoices'),

            'unit'     => UnitResource::make($this->whenLoaded('unit')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
