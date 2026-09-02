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
        $position = $this->glAccountPosition();

        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'community_id'    => $this->community_id,
            'name'            => $this->name,
            'bank_name'       => $this->bank_name,
            'account_number'  => $this->account_number,
            'branch_code'     => $this->branch_code,
            'branch_name'     => $this->branch_name,
            'integration'     => $this->integration,
            'type'            => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'balance'         => (float) $this->balance,
            'balance_as_at'   => $this->balance_as_at?->toDateString(),
            'is_active'       => (bool) $this->is_active,

            'is_default'             => (bool) $this->is_default,
            'tenant_billing_account' => (bool) $this->tenant_billing_account,
            'opening_balance'        => (float) $this->opening_balance,

            // Real GL ledger link (8000/00n Bank account).
            'ledger_id'              => $this->ledger_id,
            'general_ledger_account' => $this->generalLedgerAccountLabel($position),

            // Display-only GL derivation — retained for backwards compatibility.
            'gl_account'             => '8000/' . str_pad((string) $position, 3, '0', STR_PAD_LEFT),
            'general_ledger_code'    => '8000/' . str_pad((string) $position, 3, '0', STR_PAD_LEFT),
            'general_ledger_description' => trim($this->bank_name . ' ' . $this->account_number) ?: $this->name,

            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),

            'community'       => CommunityResource::make($this->whenLoaded('community')),
        ];
    }

    /**
     * Build the real GL account label "{code} - {name}" from the ledger relation
     * when it exists; otherwise fall back to the derived display-only label.
     *
     * @param int $position
     * @return string
     */
    protected function generalLedgerAccountLabel(int $position): string
    {
        $ledger = $this->relationLoaded('ledger') ? $this->getRelation('ledger') : $this->ledger;

        if ($ledger) {
            return $ledger->code . ' - ' . $ledger->name;
        }

        $code = '8000/' . str_pad((string) $position, 3, '0', STR_PAD_LEFT);

        return $code . ' - ' . (trim($this->bank_name . ' ' . $this->account_number) ?: $this->name);
    }

    /**
     * Derive this account's 1-based position among its community's bank accounts,
     * ordered by created_at. Used only to build the display-only GL account label.
     *
     * @return int
     */
    protected function glAccountPosition(): int
    {
        $ids = \App\Models\BankAccount::query()
            ->where('organization_id', $this->organization_id)
            ->when(
                $this->community_id !== null,
                fn ($q) => $q->where('community_id', $this->community_id),
                fn ($q) => $q->whereNull('community_id')
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $index = array_search($this->id, $ids, true);

        return $index === false ? 1 : $index + 1;
    }
}
