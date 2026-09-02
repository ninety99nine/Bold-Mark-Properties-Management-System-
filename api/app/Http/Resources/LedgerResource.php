<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'organization_id'          => $this->organization_id,
            'code'                     => $this->code,
            'category'                 => $this->category,
            'type'                     => $this->type,
            'name'                     => $this->name,
            'description'              => $this->description,
            'is_system'                => (bool) $this->is_system,
            'is_active'                => (bool) $this->is_active,
            'applies_to'               => $this->applies_to instanceof \BackedEnum ? $this->applies_to->value : $this->applies_to,
            'is_recurring'             => (bool) $this->is_recurring,
            'sort_order'               => $this->sort_order,

            // WeConnectU General Ledger classification.
            'account_type'             => $this->account_type,
            'account_type_label'       => $this->account_type === 'balance_sheet' ? 'Balance Sheet' : ($this->account_type === 'income_statement' ? 'Income Statement' : null),
            'financial_category'       => $this->financial_category?->value,
            'financial_category_label' => $this->financial_category?->label(),
            'tax_type'                 => $this->tax_type?->value,
            'tax_type_label'           => $this->tax_type?->label(),
            'fund'                     => $this->fund,
            'parent_id'                => $this->parent_id,
            'allow_sub_accounts'       => (bool) $this->allow_sub_accounts,
            'is_budget_item'           => (bool) $this->is_budget_item,

            'created_at'               => $this->created_at?->toDateTimeString(),
            'updated_at'               => $this->updated_at?->toDateTimeString(),

            'sub_accounts' => LedgerResource::collection($this->whenLoaded('subAccounts')),
            'communities'  => CommunityResource::collection($this->whenLoaded('communities')),
        ];
    }
}
