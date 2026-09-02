<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UnitDocumentResource extends JsonResource
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
            'name'             => $this->name,
            'original_name'    => $this->original_name,
            'mime_type'        => $this->mime_type,
            'size'             => (int) $this->size,
            'download_url'     => $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
            'uploaded_by_name' => $this->uploaded_by_name,
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}
