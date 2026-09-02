<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceChecklistItemResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'compliance_checklist_id'  => $this->compliance_checklist_id,
            'organization_id'                => $this->organization_id,
            'name'                     => $this->name,
            'category'                 => $this->category,
            'description'              => $this->description,
            'status'                   => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'priority'                 => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'due_date'                 => $this->due_date?->toDateString(),
            'sort_order'               => (int) $this->sort_order,
            'is_recurring'             => (bool) $this->is_recurring,
            'completion_notes'         => $this->completion_notes,
            'evidence_file_path'       => $this->evidence_file_path,
            'evidence_file_name'       => $this->evidence_file_name,
            'has_evidence'             => ! empty($this->evidence_file_path) || $this->whenLoaded('attachments', fn () => $this->attachments->isNotEmpty(), false),
            'completed_at'             => $this->completed_at?->toDateTimeString(),
            'assigned_to_id'           => $this->assigned_to_id,
            'completed_by_id'          => $this->completed_by_id,

            // Relations (when loaded)
            'assigned_to'              => new UserResource($this->whenLoaded('assignedTo')),
            'completed_by'             => new UserResource($this->whenLoaded('completedBy')),
            'attachments'              => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id'          => $a->id,
                'file_name'   => $a->file_name,
                'file_size'   => $a->file_size,
                'mime_type'   => $a->mime_type,
                'uploaded_by' => $a->uploadedBy ? ['id' => $a->uploadedBy->id, 'name' => $a->uploadedBy->name] : null,
                'created_at'  => $a->created_at?->toDateTimeString(),
            ])),

            'created_at'               => $this->created_at?->toDateTimeString(),
            'updated_at'               => $this->updated_at?->toDateTimeString(),
        ];
    }
}
