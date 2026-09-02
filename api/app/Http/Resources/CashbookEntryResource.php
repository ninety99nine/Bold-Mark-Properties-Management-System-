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
            'community_id'       => $this->community_id,
            'organization_id'       => $this->organization_id,
            'unit_id'         => $this->unit_id,
            'invoice_id'      => $this->invoice_id,
            'bank_account_id' => $this->bank_account_id,
            'ledger_id'  => $this->ledger_id,
            'supplier_id' => $this->supplier_id,
            'parent_entry_id' => $this->parent_entry_id,
            'description'     => $this->description,
            'amount'          => (float) $this->amount,
            'type'            => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'date'            => $this->date?->toDateString(),
            'notes'                => $this->notes,
            'proof_of_payment_url' => $this->proof_of_payment_path
                ? Storage::disk('public')->url($this->proof_of_payment_path)
                : null,
            'allocated_by_name' => $this->allocated_by_name,
            'allocated_at'      => ($this->allocated_at ?? $this->created_at)?->toDateTimeString(),
            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),

            'is_allocated' => $this->is_allocated,
            'is_split'     => (bool) $this->is_split,
            'allocation_ledger_type' => $this->allocation_ledger_type instanceof \BackedEnum
                ? $this->allocation_ledger_type->value
                : $this->allocation_ledger_type,
            'vat_type'           => $this->vat_type,
            'allocation_remarks' => $this->allocation_remarks,
            'account_label'      => app(\App\Services\AllocationPostingService::class)->accountLabelFor($this->resource),

            'community'        => CommunityResource::make($this->whenLoaded('community')),
            'unit'          => UnitResource::make($this->whenLoaded('unit')),
            'invoice'       => InvoiceResource::make($this->whenLoaded('invoice')),
            'ledger'   => LedgerResource::make($this->whenLoaded('ledger')),
            'parent_entry'  => CashbookEntryResource::make($this->whenLoaded('parentEntry')),
            'child_entries' => CashbookEntryResource::collection($this->whenLoaded('childEntries')),
        ];
    }
}
