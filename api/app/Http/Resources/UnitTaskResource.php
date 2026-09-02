<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitTaskResource extends JsonResource
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
            'code'             => $this->code,
            'title'            => $this->title,
            'description'      => $this->description,
            'category'         => $this->category,
            'task_type'        => $this->task_type,
            'area'             => $this->area,
            'recurring_type'   => $this->recurring_type,
            'assignee_name'    => $this->assignee_name,
            'assignee_user_id' => $this->assignee_user_id,
            'status'           => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'status_label'     => $this->status instanceof \App\Enums\TaskStatus ? $this->status->label() : ucfirst((string) $this->status),
            'is_overdue'       => $this->is_overdue,
            'community_id'     => $this->community_id,
            'community_name'   => $this->whenLoaded('community', fn () => $this->community?->name),
            'unit_number'      => $this->whenLoaded('unit', fn () => $this->unit?->unit_number),
            'internal'         => (bool) $this->internal,
            'due_date'         => $this->due_date?->toDateString(),
            'contacts'         => $this->contacts ?? [],
            'supplier_names'   => $this->supplier_names ?? [],
            'attachment_names' => $this->attachment_names ?? [],
            'created_by_name'  => $this->created_by_name,
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updates_count'    => $this->whenCounted('updates'),
            'updates'          => UnitTaskUpdateResource::collection($this->whenLoaded('updates')),
        ];
    }
}
