<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderResources;

class OrderService extends BaseService
{
    /**
     * Show orders.
     *
     * @param Customer $customer
     * @param array $data
     * @return OrderResources|array
     */
    public function showOrders(Customer $customer, array $data): OrderResources|array
    {
        $currency       = $data['currency'] ?? null;
        $country        = $data['country'] ?? null;
        $channel        = $data['channel'] ?? null;
        $hasDisputes    = $data['has_disputes'] ?? null;
        $fulfilled      = $data['fulfilled'] ?? null;
        $dateRange      = $data['date_range'] ?? null;
        $dateRangeEnd   = $data['date_range_end'] ?? null;
        $dateRangeStart = $data['date_range_start'] ?? null;

        $query = Order::where('customer_id', $customer->id);

        if (!empty($currency))    $query->where('currency', $currency);
        if (!empty($country))     $query->where('country', $country);
        if (!empty($channel))     $query->where('channel', $channel);
        if ($fulfilled !== null)  $query->where('fulfilled', $fulfilled);
        if ($hasDisputes !== null) $query->where('open_disputes_count', $hasDisputes ? '>' : '=', 0);

        if ($dateRange) {
            $query = $this->applyDateRange($query, $dateRange, $dateRangeStart, $dateRangeEnd);
        }

        if (!request()->has('_sort')) $query = $query->latest();

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Show orders summary.
     *
     * @param Customer $customer
     * @param array $data
     * @return array
     */
    public function showOrdersSummary(Customer $customer, array $data): array
    {
        $currency       = $data['currency'] ?? null;
        $country        = $data['country'] ?? null;
        $channel        = $data['channel'] ?? null;
        $fulfilled      = $data['fulfilled'] ?? null;
        $hasDisputes    = $data['has_disputes'] ?? false;
        $dateRange      = $data['date_range'] ?? null;
        $dateRangeEnd   = $data['date_range_end'] ?? null;
        $dateRangeStart = $data['date_range_start'] ?? null;

        $query = Order::where('customer_id', $customer->id);

        if (!empty($currency))    $query->where('currency', $currency);
        if (!empty($country))     $query->where('country', $country);
        if (!empty($channel))     $query->where('channel', $channel);
        if ($fulfilled !== null)  $query->where('fulfilled', $fulfilled);
        if ($hasDisputes)         $query->where('open_disputes_count', '>', 0);

        $query = $this->applyDateRange($query, $dateRange, $dateRangeStart, $dateRangeEnd);
        $query = $this->setQuery($query)->applySearchOnQuery()->getQuery();

        $stats = (clone $query)->selectRaw('
            COUNT(*) as total,
            SUM(fulfilled) as fulfilled_count,
            SUM(CASE WHEN fulfilled = 0 THEN 1 ELSE 0 END) as unfulfilled_count,
            AVG(total_amount) as avg_order_value,
            SUM(total_amount) as total_revenue,
            SUM(CASE WHEN channel = "mobile" THEN 1 ELSE 0 END) as mobile_count,
            SUM(CASE WHEN channel = "web" THEN 1 ELSE 0 END) as web_count
        ')->first();

        $total           = (int) ($stats->total ?? 0);
        $fulfilledCount  = (int) ($stats->fulfilled_count ?? 0);
        $unfulfilledCount = (int) ($stats->unfulfilled_count ?? 0);
        $mobileCount     = (int) ($stats->mobile_count ?? 0);
        $webCount        = (int) ($stats->web_count ?? 0);

        $fulfilmentRate  = $total > 0 ? round(($fulfilledCount / $total) * 100, 1) : 0;
        $mobilePct       = $total > 0 ? round(($mobileCount / $total) * 100, 1) : 0;
        $webPct          = $total > 0 ? round(($webCount / $total) * 100, 1) : 0;

        return [
            'total_orders'       => $total,
            'fulfilled_orders'   => $fulfilledCount,
            'unfulfilled_orders' => $unfulfilledCount,
            'fulfilment_rate'    => $fulfilmentRate,
            'avg_order_value'    => round($stats->avg_order_value ?? 0, 2),
            'total_revenue'      => (int) ($stats->total_revenue ?? 0),
            'mobile_orders'      => $mobileCount,
            'web_orders'         => $webCount,
            'mobile_percentage'  => $mobilePct,
            'web_percentage'     => $webPct,
        ];
    }

    /**
     * Show order.
     *
     * @param Customer $customer
     * @param Order $order
     * @return OrderResource
     */
    public function showOrder(Customer $customer, Order $order): OrderResource
    {
        return $this->showResource($order);
    }
}
