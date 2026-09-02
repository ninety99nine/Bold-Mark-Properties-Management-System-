<?php

namespace App\Http\Resources;

use App\Enums\JournalEntryType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalLineResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $entryType = $this->entry_type instanceof \BackedEnum ? $this->entry_type->value : $this->entry_type;
        $lineType  = $this->line_type instanceof \BackedEnum ? $this->line_type->value : $this->line_type;

        return [
            'id'          => $this->id,
            'line_type'   => $lineType,
            'ledger_id'   => $this->ledger_id,
            'unit_id'     => $this->unit_id,
            'description' => $this->description,
            'amount'      => (float) $this->amount,
            'entry_type'  => $entryType,
            'sort_order'  => (int) $this->sort_order,

            // Convenience label + signed amount for read-only views (batch detail).
            'account'        => $this->accountLabel(),
            'signed_amount'  => $entryType === JournalEntryType::DEBIT->value
                ? (float) $this->amount
                : -1 * (float) $this->amount,

            'ledger' => LedgerResource::make($this->whenLoaded('ledger')),
            'unit'   => UnitResource::make($this->whenLoaded('unit')),
        ];
    }

    /**
     * Human-readable account label matching WeConnectU:
     *   general / reserve_fund → "1000/001 - Levies"
     *   customer               → "AMM001-U80 - A M Morule & AB Nkomo"
     *
     * @return string|null
     */
    private function accountLabel(): ?string
    {
        if ($this->relationLoaded('ledger') && $this->ledger) {
            return $this->ledger->code
                ? $this->ledger->code . ' - ' . $this->ledger->name
                : $this->ledger->name;
        }

        if ($this->relationLoaded('unit') && $this->unit) {
            $name = $this->unit->owner?->full_name
                ?? $this->unit->currentOccupant?->full_name
                ?? ('Unit ' . $this->unit->unit_number);

            $code = $this->unit->customer_code ?? $this->unit->unit_number;

            return $code . ' - ' . $name;
        }

        return null;
    }
}
