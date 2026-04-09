<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Demonstrates: computed rates/percentages with division-by-zero guard,
 * !is_null() boolean pattern for nullable flag columns, and addSelect
 * computed columns passed through from the query layer.
 */
class CustomerResource extends BaseResource
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
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone ?? null,
            'country'    => $this->country,
            'status'     => $this->status,
            'is_active'  => $this->is_active ? true : false,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),

            'total_orders'            => $this->total_orders,
            'total_completed_orders'  => $this->total_completed_orders,
            'total_cancelled_orders'  => $this->total_cancelled_orders,
            'completion_rate'         => $this->total_orders > 0
                ? round(($this->total_completed_orders / $this->total_orders) * 100, 1)
                : 0,
            'cancellation_rate'       => $this->total_orders > 0
                ? round(($this->total_cancelled_orders / $this->total_orders) * 100, 1)
                : 0,
            'total_spend'             => $this->total_spend,
            'average_order_value'     => $this->total_orders > 0
                ? round($this->total_spend / $this->total_orders, 2)
                : 0,
            'open_disputes_count'     => $this->open_disputes_count,
            'suspended'               => !is_null($this->suspended_at),
            'suspended_at'            => $this->suspended_at?->toDateTimeString(),

            // Relationships
            'recent_orders' => OrderResource::collection($this->whenLoaded('recentOrders')),
        ];
    }
}
