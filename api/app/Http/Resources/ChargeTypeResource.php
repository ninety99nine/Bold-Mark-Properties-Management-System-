<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeTypeResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'tenant_id'    => $this->tenant_id,
            'code'         => $this->code,
            'name'         => $this->name,
            'description'  => $this->description,
            'is_system'    => (bool) $this->is_system,
            'is_active'    => (bool) $this->is_active,
            'applies_to'   => $this->applies_to instanceof \BackedEnum ? $this->applies_to->value : $this->applies_to,
            'is_recurring' => (bool) $this->is_recurring,
            'sort_order'   => $this->sort_order,
            'created_at'   => $this->created_at?->toDateTimeString(),
            'updated_at'   => $this->updated_at?->toDateTimeString(),

            'estates' => EstateResource::collection($this->whenLoaded('estates')),
        ];
    }
}
