<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierInvoiceItemResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'account_name' => $this->account_name ?? $this->whenLoaded('ledger', fn () => $this->ledger?->name),
            'account_label' => $this->whenLoaded('ledger', function () {
                if ($this->ledger) {
                    return trim(($this->ledger->code ? $this->ledger->code . ': ' : '') . $this->ledger->name);
                }
                return $this->account_name;
            }, $this->account_name),
            'ledger_code'  => $this->whenLoaded('ledger', fn () => $this->ledger?->code),
            'description'  => $this->description,
            'quantity'     => (float) $this->quantity,
            'unit_price'   => (float) $this->unit_price,
            'discount'     => (float) $this->discount,
            'tax_rate'     => (float) $this->tax_rate,
            'tax_amount'   => (float) $this->tax_amount,
            'line_total'   => (float) $this->line_total,
            'sort_order'   => (int) $this->sort_order,
            'ledger_id'    => $this->ledger_id,
        ];
    }
}
