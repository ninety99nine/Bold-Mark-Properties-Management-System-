<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'tenant_id'        => $this->tenant_id,
            'context'          => $this->context,
            'name'             => $this->name,
            'date_range'       => $this->date_range,
            'date_range_start' => $this->date_range_start?->toDateString(),
            'date_range_end'   => $this->date_range_end?->toDateString(),
            'filters'          => $this->filters ?? [],
            'sort_field'       => $this->sort_field,
            'sort_direction'   => $this->sort_direction,
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),
        ];
    }
}
