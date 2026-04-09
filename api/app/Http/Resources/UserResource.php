<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,
            'email_verified_at'   => $this->email_verified_at?->toDateTimeString(),
            'phone'               => $this->phone,
            'status'              => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'last_login_at'       => $this->last_login_at?->toDateTimeString(),
            'tenant_id'           => $this->tenant_id,
            'created_at'          => $this->created_at?->toDateTimeString(),
            'updated_at'          => $this->updated_at?->toDateTimeString(),

            'email_verified'      => !is_null($this->email_verified_at),

            'tenant'  => TenantResource::make($this->whenLoaded('tenant')),
            'roles'   => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($r) => [
                'id'   => $r->id,
                'name' => $r->name,
            ])),
            'estates' => $this->whenLoaded('estates', fn () => $this->estates->map(fn ($e) => [
                'id'   => $e->id,
                'name' => $e->name,
            ])),
        ];
    }
}
