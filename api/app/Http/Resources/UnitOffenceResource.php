<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitOffenceResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'unit_id'          => $this->unit_id,
            'status'           => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'issued_date'      => $this->issued_date?->toDateString(),
            'description'      => $this->description,
            'rules'            => $this->rules ?? [],
            'attachment_names' => $this->attachment_names ?? [],
            'created_by_name'  => $this->created_by_name,
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}
