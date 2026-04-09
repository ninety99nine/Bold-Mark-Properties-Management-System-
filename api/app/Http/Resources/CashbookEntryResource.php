<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CashbookEntryResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'estate_id'       => $this->estate_id,
            'tenant_id'       => $this->tenant_id,
            'unit_id'         => $this->unit_id,
            'invoice_id'      => $this->invoice_id,
            'charge_type_id'  => $this->charge_type_id,
            'parent_entry_id' => $this->parent_entry_id,
            'description'     => $this->description,
            'amount'          => (float) $this->amount,
            'type'            => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'date'            => $this->date?->toDateString(),
            'notes'                => $this->notes,
            'proof_of_payment_url' => $this->proof_of_payment_path
                ? Storage::disk('public')->url($this->proof_of_payment_path)
                : null,
            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),

            'is_allocated' => $this->is_allocated,

            'estate'        => EstateResource::make($this->whenLoaded('estate')),
            'unit'          => UnitResource::make($this->whenLoaded('unit')),
            'invoice'       => InvoiceResource::make($this->whenLoaded('invoice')),
            'charge_type'   => ChargeTypeResource::make($this->whenLoaded('chargeType')),
            'parent_entry'  => CashbookEntryResource::make($this->whenLoaded('parentEntry')),
            'child_entries' => CashbookEntryResource::collection($this->whenLoaded('childEntries')),
        ];
    }
}
