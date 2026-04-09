<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'tenant_id'      => $this->tenant_id,
            'unit_id'        => $this->unit_id,
            'charge_type_id' => $this->charge_type_id,
            'billed_to_type' => $this->billed_to_type instanceof \BackedEnum ? $this->billed_to_type->value : $this->billed_to_type,
            'billed_to_id'   => $this->billed_to_id,
            'invoice_number' => $this->invoice_number,
            'status'         => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'amount'         => (float) $this->amount,
            'billing_period' => $this->billing_period?->toDateString(),
            'due_date'       => $this->due_date?->toDateString(),
            'sent_at'            => $this->sent_at?->toDateTimeString(),
            'issued_by_type'     => $this->issued_by_type,
            'issued_by_user_id'  => $this->issued_by_user_id,
            'created_at'         => $this->created_at?->toDateTimeString(),
            'updated_at'         => $this->updated_at?->toDateTimeString(),
            'deleted_at'         => $this->deleted_at?->toDateTimeString(),

            'is_paid'     => $this->is_paid,
            'total_paid'  => $this->total_paid,
            'outstanding' => $this->outstanding,

            'cashbook_entries_count' => $this->whenCounted('cashbookEntries'),

            'unit'                  => UnitResource::make($this->whenLoaded('unit')),
            'charge_type'           => ChargeTypeResource::make($this->whenLoaded('chargeType')),
            'cashbook_entries'      => CashbookEntryResource::collection($this->whenLoaded('cashbookEntries')),
            'billed_to_owner'       => OwnerResource::make($this->whenLoaded('billedToOwner')),
            'billed_to_unit_tenant' => UnitTenantResource::make($this->whenLoaded('billedToUnitTenant')),
            'email_events'          => InvoiceEmailEventResource::collection($this->whenLoaded('emailEvents')),
            'issued_by'             => $this->whenLoaded('issuedBy', fn() => [
                'id'   => $this->issuedBy?->id,
                'name' => $this->issuedBy?->name,
            ]),
        ];
    }
}
