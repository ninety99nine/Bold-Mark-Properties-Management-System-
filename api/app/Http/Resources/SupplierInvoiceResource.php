<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierInvoiceResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'grv_number'             => $this->grv_number,
            'status'                 => $this->status?->value,
            'status_label'           => $this->status?->label(),
            'type'                   => $this->type?->value,
            'type_label'             => $this->type?->label(),
            'source_document_number' => $this->source_document_number,
            'supplier_reference'     => $this->supplier_reference,
            'our_reference'          => $this->our_reference,
            'description'            => $this->description,
            'attachments'            => $this->attachments ?? [],
            'invoice_date'           => $this->invoice_date?->toDateString(),
            'due_date'               => $this->due_date?->toDateString(),
            'financial_year'         => $this->financial_year,
            'subtotal'           => (float) $this->subtotal,
            'discount'           => (float) $this->discount,
            'vat_amount'         => (float) $this->vat_amount,
            'total'              => (float) $this->total,
            'supplier_id'        => $this->supplier_id,
            'supplier_code'      => $this->whenLoaded('supplier', fn () => $this->supplier?->supplier_code),
            'supplier_name'      => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'community_id'       => $this->community_id,
            'created_at'         => $this->created_at?->toDateTimeString(),
            'updated_at'         => $this->updated_at?->toDateTimeString(),

            'supplier'           => SupplierResource::make($this->whenLoaded('supplier')),
            'items'              => SupplierInvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
