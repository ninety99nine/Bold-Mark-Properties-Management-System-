<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitCollectionNoteResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'unit_id'         => $this->unit_id,
            'note'            => $this->note,
            'created_by_name' => $this->created_by_name,
            'created_at'      => $this->created_at?->toDateTimeString(),
        ];
    }
}
