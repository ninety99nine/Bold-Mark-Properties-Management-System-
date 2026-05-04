<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceChecklistResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'organization_id'             => $this->organization_id,
            'estate_id'             => $this->estate_id,
            'financial_year_label'  => $this->financial_year_label,
            'financial_year_start'  => $this->financial_year_start?->toDateString(),
            'financial_year_end'    => $this->financial_year_end?->toDateString(),
            'notes'                 => $this->notes,
            'created_by_id'         => $this->created_by_id,

            // Counts (when loaded via withCount)
            'items_count'           => $this->whenCounted('items'),
            'completed_items_count' => $this->whenCounted('completedItems'),
            'overdue_items_count'   => $this->whenCounted('overdueItems'),

            // Computed progress (appended by service when available)
            'progress_percentage'   => $this->when(isset($this->progress_percentage), $this->progress_percentage),
            'status_summary'        => $this->when(isset($this->status_summary), $this->status_summary),

            // Relations (when loaded)
            'estate'                => new EstateResource($this->whenLoaded('estate')),
            'created_by'            => new UserResource($this->whenLoaded('createdBy')),
            'items'                 => ComplianceChecklistItemResource::collection($this->whenLoaded('items')),

            'created_at'            => $this->created_at?->toDateTimeString(),
            'updated_at'            => $this->updated_at?->toDateTimeString(),
        ];
    }
}
