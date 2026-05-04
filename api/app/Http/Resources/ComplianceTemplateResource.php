<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceTemplateResource extends JsonResource
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
            'country'     => $this->country,
            'is_default'  => (bool) $this->is_default,
            'is_system'   => (bool) $this->is_system,

            'items_count' => $this->whenCounted('items'),
            'items'       => ComplianceTemplateItemResource::collection($this->whenLoaded('items')),

            'created_at'  => $this->created_at?->toDateTimeString(),
            'updated_at'  => $this->updated_at?->toDateTimeString(),
        ];
    }
}
