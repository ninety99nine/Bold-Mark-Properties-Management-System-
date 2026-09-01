<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
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
            'company_reg_no'   => $this->company_reg_no,
            'transfer_clearance_fee' => $this->transfer_clearance_fee,
            'logo_url'         => $this->logo_url,
            'icon_url'         => $this->icon_url,
            'email_header_url' => $this->email_header_url,
            'email_footer_url' => $this->email_footer_url,
            'contact_email'    => $this->contact_email,
            'outgoing_email'   => $this->outgoing_email,
            'contact_phone'    => $this->contact_phone,
            'address'          => $this->address,
            'country'          => $this->country,
            'currency'         => $this->currency,
            'bank_account_holder' => $this->bank_account_holder,
            'bank_name'           => $this->bank_name,
            'bank_account_type'   => $this->bank_account_type,
            'bank_account_number' => $this->bank_account_number,
            'bank_branch_code'    => $this->bank_branch_code,
            'bank_branch_name'    => $this->bank_branch_name,
            'primary_color'    => $this->primary_color,
            'secondary_color'  => $this->secondary_color,
            'copyright_name'   => $this->copyright_name,
            'is_active'        => (bool) $this->is_active,
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),

            'users'         => UserResource::collection($this->whenLoaded('users')),
            'communities'       => CommunityResource::collection($this->whenLoaded('communities')),
            'ledgers'  => LedgerResource::collection($this->whenLoaded('ledgers')),
        ];
    }
}
