<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiskRuleResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'organization_id'   => $this->organization_id,
            'name'        => $this->name,
            'description' => $this->description,
            'severity'    => $this->severity instanceof \BackedEnum ? $this->severity->value : $this->severity,
            'conditions'  => $this->conditions,
            'is_active'   => (bool) $this->is_active,
            'sort_order'  => (int) $this->sort_order,
            'created_at'  => $this->created_at?->toDateTimeString(),
            'updated_at'  => $this->updated_at?->toDateTimeString(),
        ];
    }
}
