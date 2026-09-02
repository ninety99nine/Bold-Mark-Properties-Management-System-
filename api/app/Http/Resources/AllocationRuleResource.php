<?php

namespace App\Http\Resources;

use App\Services\AllocationPostingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllocationRuleResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $posting = app(AllocationPostingService::class);

        $accountLabel = match ($this->ledger_type) {
            \App\Enums\JournalLineType::GENERAL,
            \App\Enums\JournalLineType::RESERVE_FUND => $posting->ledgerLabel($this->ledger_id),
            \App\Enums\JournalLineType::CUSTOMER     => $posting->customerLabel($this->unit_id),
            \App\Enums\JournalLineType::SUPPLIER     => $posting->supplierLabel($this->supplier_id),
            default                                  => null,
        };

        return [
            'id'                      => $this->id,
            'organization_id'         => $this->organization_id,
            'community_id'            => $this->community_id,
            'description_starts_with' => $this->description_starts_with,
            'description_contains'    => $this->description_contains,
            'ledger_type'             => $this->ledger_type?->value,
            'ledger_id'               => $this->ledger_id,
            'unit_id'                 => $this->unit_id,
            'supplier_id'             => $this->supplier_id,
            'account_label'           => $accountLabel,
            'vat_type'                => $this->vat_type,
            'remarks'                 => $this->remarks,
            'apply_to_positive'       => (bool) $this->apply_to_positive,
            'apply_to_negative'       => (bool) $this->apply_to_negative,
            'bank_account_ids'        => $this->bank_account_ids,
            'sort_order'              => (int) $this->sort_order,
            'created_at'              => $this->created_at?->toDateTimeString(),
            'updated_at'              => $this->updated_at?->toDateTimeString(),
        ];
    }
}
