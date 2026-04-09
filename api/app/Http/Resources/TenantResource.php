<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'company_name'     => $this->company_name,
            'company_slogan'   => $this->company_slogan,
            'logo_url'         => $this->logo_url,
            'contact_email'    => $this->contact_email,
            'contact_phone'    => $this->contact_phone,
            'address'          => $this->address,
            'country'          => $this->country,
            'currency'         => $this->currency,
            'primary_color'    => $this->primary_color,
            'secondary_color'  => $this->secondary_color,
            'copyright_name'   => $this->copyright_name,
            'is_active'        => (bool) $this->is_active,
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),

            'users'         => UserResource::collection($this->whenLoaded('users')),
            'estates'       => EstateResource::collection($this->whenLoaded('estates')),
            'charge_types'  => ChargeTypeResource::collection($this->whenLoaded('chargeTypes')),
        ];
    }
}
