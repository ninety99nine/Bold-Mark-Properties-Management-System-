<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitChargeConfigResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'unit_id'        => $this->unit_id,
            'tenant_id'      => $this->tenant_id,
            'charge_type_id' => $this->charge_type_id,
            'amount'         => (float) $this->amount,
            'is_active'      => (bool) $this->is_active,
            'created_at'     => $this->created_at?->toDateTimeString(),
            'updated_at'     => $this->updated_at?->toDateTimeString(),

            'unit'        => UnitResource::make($this->whenLoaded('unit')),
            'charge_type' => ChargeTypeResource::make($this->whenLoaded('chargeType')),
        ];
    }
}
