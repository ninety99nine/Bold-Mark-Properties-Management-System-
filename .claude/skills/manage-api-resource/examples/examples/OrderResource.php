<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Demonstrates: dense scalar block, non-nullable timestamps (no ?->),
 * and a helper method sourced from BaseResource (e.g. getDurationStatus()).
 * Adapt helper method names to whatever your BaseResource provides.
 */
class OrderResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'reference'       => $this->reference,
            'customer_id'     => $this->customer_id,
            'currency'        => $this->currency,
            'amount'          => $this->amount,
            'tax'             => $this->tax,
            'shipping_method' => $this->shipping_method,
            'status'          => $this->status,
            'notes'           => $this->notes ?? null,
            'created_at'      => $this->created_at->toDateTimeString(),
            'updated_at'      => $this->updated_at->toDateTimeString(),

            'processing_time_ms'     => $this->processing_time_ms,
            'processing_time_status' => $this->getDurationStatus($this->processing_time_ms),
            'fulfilled'              => $this->fulfilled,
            'fulfilled_at'           => $this->fulfilled_at?->toDateTimeString(),
            'total_items'            => $this->total_items,
            'total_line_items'       => $this->total_line_items,

            // Relationships
            'customer'   => CustomerResource::make($this->whenLoaded('customer')),
            'line_items' => OrderLineItemResource::collection($this->whenLoaded('lineItems')),
            'shipment'   => ShipmentResource::make($this->whenLoaded('shipment')),
        ];
    }
}
