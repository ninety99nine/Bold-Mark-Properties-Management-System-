<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'organization_id'    => $this->organization_id,
            'unit_id'            => $this->unit_id,
            'applied_invoice_id' => $this->applied_invoice_id,
            'billed_to_type'     => $this->billed_to_type instanceof \BackedEnum ? $this->billed_to_type->value : $this->billed_to_type,
            'billed_to_id'       => $this->billed_to_id,
            'credit_note_number' => $this->credit_note_number,
            'reason'             => $this->reason,
            'order_no'           => $this->order_no,
            'reference'          => $this->reference,
            'amount'             => (float) $this->amount,
            'subtotal'           => $this->subtotal !== null ? (float) $this->subtotal : null,
            'vat_amount'         => $this->vat_amount !== null ? (float) $this->vat_amount : null,
            'credit_note_date'   => $this->credit_note_date?->toDateString(),
            'sent_at'            => $this->sent_at?->toDateTimeString(),
            'issued_by_type'     => $this->issued_by_type,
            'issued_by_user_id'  => $this->issued_by_user_id,
            'created_at'         => $this->created_at?->toDateTimeString(),
            'updated_at'         => $this->updated_at?->toDateTimeString(),
            'deleted_at'         => $this->deleted_at?->toDateTimeString(),

            'unit'                    => UnitResource::make($this->whenLoaded('unit')),
            'applied_invoice'         => InvoiceResource::make($this->whenLoaded('appliedInvoice')),
            'items'                   => CreditNoteItemResource::collection($this->whenLoaded('items')),
            'billed_to_owner'         => OwnerResource::make($this->whenLoaded('billedToOwner')),
            'billed_to_unit_occupant' => OccupantResource::make($this->whenLoaded('billedToUnitOccupant')),
            'issued_by'               => $this->whenLoaded('issuedBy', fn () => [
                'id'   => $this->issuedBy?->id,
                'name' => $this->issuedBy?->name,
            ]),
        ];
    }
}
