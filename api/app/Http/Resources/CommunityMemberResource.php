<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityMemberResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'community_id'          => $this->community_id,
            'name'                  => $this->name,
            'email'                 => $this->email,
            'cellphone'             => $this->cellphone,
            'user_type'             => $this->user_type instanceof \BackedEnum ? $this->user_type->value : $this->user_type,
            'type_label'            => $this->type_label,
            'is_director_trustee'   => (bool) $this->is_director_trustee,
            'is_payment_authoriser' => (bool) $this->is_payment_authoriser,
            'is_verified'           => (bool) $this->is_verified,
            'sort_order'            => (int) $this->sort_order,
            'created_at'            => $this->created_at?->toDateTimeString(),
        ];
    }
}
