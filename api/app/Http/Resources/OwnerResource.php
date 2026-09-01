<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'unit_id'    => $this->unit_id,
            'organization_id'  => $this->organization_id,
            'full_name'        => $this->full_name,
            'is_primary'       => (bool) $this->is_primary,
            'user_verified'    => (bool) $this->user_verified,
            'user_display_name' => $this->user_display_name,
            'email'            => $this->email,
            'secondary_emails' => $this->secondary_emails ?? [],
            'phone'            => $this->phone,
            'landline'         => $this->landline,
            'id_number'  => $this->id_number,
            'entity_type'      => $this->entity_type instanceof \BackedEnum ? $this->entity_type->value : $this->entity_type,
            'contact2_name'     => $this->contact2_name,
            'contact2_email'    => $this->contact2_email,
            'contact2_phone'    => $this->contact2_phone,
            'contact2_landline' => $this->contact2_landline,
            'customer_type'     => $this->customer_type,
            'customer_type_label' => $this->customer_type
                ? (\App\Enums\CustomerType::tryFrom($this->customer_type)?->label() ?? $this->customer_type)
                : null,
            'vat_no'            => $this->vat_no,
            'alt_email'         => $this->alt_email,
            'alt_phone'         => $this->alt_phone,
            'payment_type'      => $this->payment_type ?? 'not_specified',
            'payment_type_label' => \App\Enums\PaymentType::tryFrom($this->payment_type ?? 'not_specified')?->label()
                ?? $this->payment_type ?? 'Not Specified',
            'pdf_password'      => $this->pdf_password,
            'customer_group'    => $this->customer_group,
            'reference'         => $this->reference,
            'country'           => $this->country,
            'old_customer_code' => $this->old_customer_code,
            'customer_code'     => $this->customer_code,
            'code'              => $this->customer_code ?: $this->unit?->customer_code,
            'is_disabled'       => (bool) $this->is_disabled,
            'disabled_at'       => $this->disabled_at?->toDateTimeString(),
            'community_id'      => $this->community_id,
            'address'    => $this->address,
            'address_line_2'    => $this->address_line_2,
            'suburb'            => $this->suburb,
            'town'              => $this->town,
            'postal_code'       => $this->postal_code,
            'account_holder'    => $this->account_holder,
            'bank_name'         => $this->bank_name,
            'account_type'      => $this->account_type,
            'account_number'    => $this->account_number,
            'branch_code'       => $this->branch_code,
            'branch_name'       => $this->branch_name,
            'debit_order'         => (bool) $this->debit_order,
            'mandate_type'        => $this->mandate_type,
            'monthly_plus_amount' => $this->monthly_plus_amount,
            'collection_day'      => $this->collection_day,
            'notes'             => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            'invoices_count' => $this->whenCounted('invoices'),

            'customer_groups' => $this->whenLoaded('customerGroups', fn () => $this->customerGroups->map(fn ($group) => [
                'id'   => $group->id,
                'name' => $group->name,
            ])->values()),
            'customer_group_ids' => $this->whenLoaded('customerGroups', fn () => $this->customerGroups->pluck('id')->values()),

            'unit'     => UnitResource::make($this->whenLoaded('unit')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
