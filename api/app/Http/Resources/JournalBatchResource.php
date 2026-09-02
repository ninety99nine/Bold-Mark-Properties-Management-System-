<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalBatchResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $files = collect($this->files ?? [])->map(fn ($f) => [
            'name' => $f['name'] ?? basename($f['path'] ?? ''),
            'path' => $f['path'] ?? null,
        ])->values()->all();

        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'community_id'    => $this->community_id,
            'batch_number'    => (int) $this->batch_number,
            'batch_name'      => $this->batch_name,
            'journal_group'   => $this->journal_group,
            'date'            => $this->date?->toDateString(),
            'financial_year'  => (int) $this->financial_year,
            'files'           => $files,
            'files_count'     => count($files),
            'entries_count'   => $this->when(isset($this->lines_count), (int) $this->lines_count),
            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),

            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy ? [
                'id'   => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ] : null),

            'community' => CommunityResource::make($this->whenLoaded('community')),
            'lines'     => JournalLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
