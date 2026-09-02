<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitTaskUpdateResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'unit_task_id'     => $this->unit_task_id,
            'feedback'         => $this->feedback,
            'event'            => $this->event,
            'notify'           => $this->notify,
            'attachment_names' => $this->attachment_names ?? [],
            'created_by_name'  => $this->created_by_name,
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}
