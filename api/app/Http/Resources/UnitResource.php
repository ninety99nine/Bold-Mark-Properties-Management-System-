<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'community_id'        => $this->community_id,
            'organization_id'        => $this->organization_id,
            'unit_number'      => $this->unit_number,
            'block_number'     => $this->block_number,
            'section'          => $this->section,
            'door_number'      => $this->door_number,
            'customer_code'    => $this->customer_code,
            'address'          => $this->address,
            'rental_agent_email' => $this->rental_agent_email,
            'attorney_email'     => $this->attorney_email,
            'bondholder_email'   => $this->bondholder_email,
            'unit_notes'         => $this->unit_notes,
            'pq'               => $this->pq,
            'ratio_1'          => $this->ratio_1,
            'ratio_2'          => $this->ratio_2,
            'ratio_3'          => $this->ratio_3,
            'ratio_4'          => $this->ratio_4,
            'ratio_5'          => $this->ratio_5,
            'unit_size'        => $this->unit_size,
            'occupancy_type'   => $this->occupancy_type instanceof \BackedEnum ? $this->occupancy_type->value : $this->occupancy_type,
            'status'           => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'levy_override'    => $this->levy_override,
            'rent_amount'      => $this->rent_amount,
            'billing_pdf'      => (bool) $this->billing_pdf,
            'is_development'   => (bool) $this->is_development,
            'collection_status' => $this->collection_status instanceof \BackedEnum ? $this->collection_status->value : $this->collection_status,
            'debit_order'       => (bool) $this->debit_order,
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),

            'effective_levy_amount'   => $this->effective_levy_amount,
            'effective_reserve_levy'  => $this->effective_reserve_levy,

            // outstanding_amount = net owed on non-paid invoices (gross minus partial payments).
            // unallocated_credits = cashbook credits not yet matched to any invoice.
            // balance = stored column, kept in sync by UnitBalanceService::recalculate().
            //   negative  → in arrears  (unit owes more than credits on account)
            //   zero      → clear
            //   positive  → credit on account (advance payment or overpayment)
            'outstanding_amount'    => (float) ($this->outstanding_amount ?? 0),
            'unallocated_credits'   => (float) ($this->unallocated_credits ?? 0),
            'balance'               => (float) ($this->balance ?? 0),

            'total_occupants_count'     => $this->total_occupants_count ?? 0,

            'invoices_count'          => $this->whenCounted('invoices'),
            'cashbook_entries_count'  => $this->whenCounted('cashbookEntries'),

            'community'          => CommunityResource::make($this->whenLoaded('community')),
            'owner'           => OwnerResource::make($this->whenLoaded('owner')),
            'owners'          => OwnerResource::collection($this->whenLoaded('owners')),
            'current_occupant'  => OccupantResource::make($this->whenLoaded('currentOccupant')),
            'occupants'    => OccupantResource::collection($this->whenLoaded('occupants')),
            'charge_configs'  => UnitChargeConfigResource::collection($this->whenLoaded('chargeConfigs')),
            'collection_notes' => UnitCollectionNoteResource::collection($this->whenLoaded('collectionNotes')),
        ];
    }
}
