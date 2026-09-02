<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'community_id'    => $this->community_id,
            'name'            => $this->name,
            'bank_name'       => $this->bank_name,
            'account_number'  => $this->account_number,
            'type'            => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'balance'         => (float) $this->balance,
            'balance_as_at'   => $this->balance_as_at?->toDateString(),
            'is_active'       => (bool) $this->is_active,
            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),

            'community'       => CommunityResource::make($this->whenLoaded('community')),
        ];
    }
}
