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
            'organization_id'      => $this->organization_id,
            'unit_id'        => $this->unit_id,
            'ledger_id' => $this->ledger_id,
            'bank_account_id' => $this->bank_account_id,
            'billed_to_type' => $this->billed_to_type instanceof \BackedEnum ? $this->billed_to_type->value : $this->billed_to_type,
            'billed_to_id'   => $this->billed_to_id,
            'invoice_number' => $this->invoice_number,
            'status'         => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'source'         => $this->source instanceof \BackedEnum ? $this->source->value : $this->source,
            'amount'         => (float) $this->amount,
            'subtotal'       => $this->subtotal !== null ? (float) $this->subtotal : null,
            'vat_amount'     => $this->vat_amount !== null ? (float) $this->vat_amount : null,
            'billing_period' => $this->billing_period?->toDateString(),
            'invoice_date'   => $this->invoice_date?->toDateString(),
            'due_date'       => $this->due_date?->toDateString(),
            'attachment_path' => $this->attachment_path,
            'sent_at'            => $this->sent_at?->toDateTimeString(),
            'email_failed_at'    => $this->email_failed_at?->toDateTimeString(),
            'reminder_sent_at'   => $this->reminder_sent_at?->toDateTimeString(),
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
            'ledger'           => LedgerResource::make($this->whenLoaded('ledger')),
            'bank_account'          => BankAccountResource::make($this->whenLoaded('bankAccount')),
            'items'                 => InvoiceItemResource::collection($this->whenLoaded('items')),
            'cashbook_entries'      => CashbookEntryResource::collection($this->whenLoaded('cashbookEntries')),
            'billed_to_owner'       => OwnerResource::make($this->whenLoaded('billedToOwner')),
            'billed_to_unit_occupant' => OccupantResource::make($this->whenLoaded('billedToUnitOccupant')),
            'email_events'          => InvoiceEmailEventResource::collection($this->whenLoaded('emailEvents')),
            'issued_by'             => $this->whenLoaded('issuedBy', fn() => [
                'id'   => $this->issuedBy?->id,
                'name' => $this->issuedBy?->name,
            ]),
        ];
    }
}
