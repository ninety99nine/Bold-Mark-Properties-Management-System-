<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceTemplateItemResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'compliance_template_id' => $this->compliance_template_id,
            'name'                   => $this->name,
            'category'               => $this->category,
            'description'            => $this->description,
            'priority'               => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'default_month_due'      => $this->default_month_due,
            'sort_order'             => (int) $this->sort_order,
            'is_recurring'           => (bool) $this->is_recurring,
            'created_at'             => $this->created_at?->toDateTimeString(),
            'updated_at'             => $this->updated_at?->toDateTimeString(),
        ];
    }
}
