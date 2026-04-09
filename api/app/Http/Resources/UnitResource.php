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
            'estate_id'        => $this->estate_id,
            'tenant_id'        => $this->tenant_id,
            'unit_number'      => $this->unit_number,
            'address'          => $this->address,
            'occupancy_type'   => $this->occupancy_type instanceof \BackedEnum ? $this->occupancy_type->value : $this->occupancy_type,
            'status'           => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'levy_override'    => $this->levy_override,
            'rent_amount'      => $this->rent_amount,
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),

            'effective_levy_amount' => $this->effective_levy_amount,

            // outstanding_amount = net owed on non-paid invoices (gross minus partial payments).
            // unallocated_credits = cashbook credits not yet matched to any invoice.
            // balance = stored column, kept in sync by UnitBalanceService::recalculate().
            //   negative  → in arrears  (unit owes more than credits on account)
            //   zero      → clear
            //   positive  → credit on account (advance payment or overpayment)
            'outstanding_amount'    => (float) ($this->outstanding_amount ?? 0),
            'unallocated_credits'   => (float) ($this->unallocated_credits ?? 0),
            'balance'               => (float) ($this->balance ?? 0),

            'total_tenants_count'     => $this->total_tenants_count ?? 0,

            'invoices_count'          => $this->whenCounted('invoices'),
            'cashbook_entries_count'  => $this->whenCounted('cashbookEntries'),

            'estate'          => EstateResource::make($this->whenLoaded('estate')),
            'owner'           => OwnerResource::make($this->whenLoaded('owner')),
            'current_tenant'  => UnitTenantResource::make($this->whenLoaded('currentTenant')),
            'unit_tenants'    => UnitTenantResource::collection($this->whenLoaded('unitTenants')),
            'charge_configs'  => UnitChargeConfigResource::collection($this->whenLoaded('chargeConfigs')),
        ];
    }
}
