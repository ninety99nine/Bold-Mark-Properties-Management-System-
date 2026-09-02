<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'invoice_id'  => $this->invoice_id,
            'ledger_id'   => $this->ledger_id,
            'description' => $this->description,
            'quantity'    => (float) $this->quantity,
            'amount'      => (float) $this->amount,
            'tax_rate'    => (float) $this->tax_rate,
            'tax_amount'  => (float) $this->tax_amount,
            'line_total'  => (float) $this->line_total,
            'sort_order'  => (int) $this->sort_order,

            'ledger'      => LedgerResource::make($this->whenLoaded('ledger')),
        ];
    }
}
