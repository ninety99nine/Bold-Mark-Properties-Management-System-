<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SplitTemplateResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'community_id'    => $this->community_id,
            'name'            => $this->name,
            'lines'           => $this->lines ?? [],
            'lines_count'     => count($this->lines ?? []),
            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),
        ];
    }
}
