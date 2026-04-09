<?php

namespace App\Services;

use Exception;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderItemResources;

class OrderItemService extends BaseService
{
    /**
     * Show order items.
     *
     * @param Customer $customer
     * @param array $data
     * @return OrderItemResources|array
     */
    public function showOrderItems(Customer $customer, array $data): OrderItemResources|array
    {
        $status         = $data['status'] ?? null;
        $channel        = $data['channel'] ?? null;
        $hasDisputes    = $data['has_disputes'] ?? null;
        $fulfilled      = $data['fulfilled'] ?? null;
        $dateRange      = $data['date_range'] ?? null;
        $dateRangeEnd   = $data['date_range_end'] ?? null;
        $dateRangeStart = $data['date_range_start'] ?? null;

        $query = OrderItem::where('customer_id', $customer->id);

        if (!empty($channel))    $query->where('channel', $channel);
        if ($fulfilled !== null) $query->where('fulfilled', $fulfilled ? 1 : 0);
        if (!empty($status))     $query->where('status', $status);
        if ($hasDisputes !== null) $query->where('open_disputes_count', $hasDisputes ? '>' : '=', 0);

        $query->addSelect([
            'total_sub_items' => Order::selectRaw('COUNT(*)')
                ->whereColumn('orders.order_item_id', 'order_items.id'),
        ]);

        $query->addSelect([
            'completion_rate' => Order::selectRaw('ROUND(IF(COUNT(*) > 0, (SUM(fulfilled) / COUNT(*)) * 100, 0), 1)')
                ->whereColumn('orders.order_item_id', 'order_items.id'),
        ]);

        if ($dateRange) {
            $query = $this->applyDateRange($query, $dateRange, $dateRangeStart, $dateRangeEnd);
        }

        if (!request()->has('_sort')) $query = $query->latest();

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Show order items summary.
     *
     * @param Customer $customer
     * @param array $data
     * @return array
     */
    public function showOrderItemsSummary(Customer $customer, array $data): array
    {
        $status         = $data['status'] ?? null;
        $channel        = $data['channel'] ?? null;
        $hasDisputes    = $data['has_disputes'] ?? null;
        $fulfilled      = $data['fulfilled'] ?? null;
        $dateRange      = $data['date_range'] ?? null;
        $dateRangeEnd   = $data['date_range_end'] ?? null;
        $dateRangeStart = $data['date_range_start'] ?? null;

        $query = OrderItem::where('customer_id', $customer->id);

        if (!empty($channel))    $query->where('channel', $channel);
        if ($fulfilled !== null) $query->where('fulfilled', $fulfilled ? 1 : 0);
        if (!empty($status))     $query->where('status', $status);
        if ($hasDisputes !== null) $query->where('open_disputes_count', $hasDisputes ? '>' : '=', 0);

        $query = $this->applyDateRange($query, $dateRange, $dateRangeStart, $dateRangeEnd);
        $query = $this->setQuery($query)->applySearchOnQuery()->getQuery();

        $totalItems = (clone $query)->count();

        $now          = now();
        $currentStart = null;
        $currentEnd   = $now;
        $periodLabel  = 'This month';

        if ($dateRangeStart && $dateRangeEnd) {
            try {
                $currentStart = Carbon::parse($dateRangeStart)->startOfDay();
                $currentEnd   = Carbon::parse($dateRangeEnd)->endOfDay();
                $periodLabel  = 'This period';
            } catch (\Exception $e) {
            }
        } elseif ($dateRange) {
            switch ($dateRange) {
                case 'today':
                    $currentStart = $now->clone()->startOfDay();
                    $periodLabel  = 'Today';
                    break;
                case 'this_week':
                    $currentStart = $now->clone()->startOfWeek()->startOfDay();
                    $periodLabel  = 'This week';
                    break;
                case 'this_month':
                    $currentStart = $now->clone()->startOfMonth()->startOfDay();
                    $periodLabel  = 'This month';
                    break;
                case 'this_year':
                    $currentStart = $now->clone()->startOfYear()->startOfDay();
                    $periodLabel  = 'This year';
                    break;
                default:
                    $currentStart = $now->clone()->startOfMonth()->startOfDay();
                    break;
            }
        }

        if (!$currentStart) {
            $currentStart = $now->clone()->startOfMonth()->startOfDay();
            $periodLabel  = 'This month';
        }

        $newThisPeriod = (clone $query)
            ->where('created_at', '>=', $currentStart)
            ->where('created_at', '<=', $currentEnd)
            ->count();

        $newDisplay = $newThisPeriod >= 0 ? "+$newThisPeriod" : (string) $newThisPeriod;

        $activeCount = (clone $query)
            ->whereNull('cancelled_at')
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        $activePercentage = $totalItems > 0 ? round(($activeCount / $totalItems) * 100, 1) : 0;

        $avgOrders = (clone $query)->avg(DB::raw('(
            SELECT COUNT(*)
            FROM orders
            WHERE orders.order_item_id = order_items.id
        )')) ?? 0;

        $orderItemIds    = (clone $query)->pluck('id');
        $avgDurationDays = 0;

        if ($orderItemIds->isNotEmpty()) {
            $durationStats = Order::whereIn('order_item_id', $orderItemIds)
                ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
                ->first();

            $avgDurationDays = round((float) ($durationStats->avg_days ?? 0), 1);
        }

        $channelStats = (clone $query)
            ->select('channel', DB::raw('COUNT(*) as count'))
            ->groupBy('channel')
            ->get()
            ->mapWithKeys(function ($item) use ($totalItems) {
                $percentage = $totalItems > 0 ? round(($item->count / $totalItems) * 100, 1) : 0;
                return [($item->channel ?? 'Unknown') => $percentage];
            })
            ->toArray();

        return [
            'total_items'             => $totalItems,
            'new_this_period'         => $newThisPeriod,
            'new_this_period_display' => $newDisplay,
            'period_label'            => $periodLabel,
            'active_items'            => $activeCount,
            'active_percentage'       => $activePercentage,
            'avg_orders'              => round($avgOrders),
            'avg_duration_days'       => $avgDurationDays,
            'channel_breakdown'       => $channelStats,
        ];
    }

    /**
     * Fulfill order items.
     *
     * @param Customer $customer
     * @param array $orderItemIds
     * @return array
     * @throws Exception
     */
    public function fulfillOrderItems(Customer $customer, array $orderItemIds): array
    {
        $items = OrderItem::where('customer_id', $customer->id)->whereIn('id', $orderItemIds)->get();

        if ($total = $items->count()) {
            foreach ($items as $item) {
                $this->fulfillOrderItem($customer, $item);
            }
            return ['message' => $total . ($total === 1 ? ' item' : ' items') . ' fulfilled'];
        }

        throw new Exception('No items fulfilled');
    }

    /**
     * Cancel order items.
     *
     * @param Customer $customer
     * @param array $orderItemIds
     * @return array
     * @throws Exception
     */
    public function cancelOrderItems(Customer $customer, array $orderItemIds): array
    {
        $items = OrderItem::where('customer_id', $customer->id)->whereIn('id', $orderItemIds)->get();

        if ($total = $items->count()) {
            foreach ($items as $item) {
                $this->cancelOrderItem($customer, $item);
            }
            return ['message' => $total . ($total === 1 ? ' item' : ' items') . ' cancelled'];
        }

        throw new Exception('No items cancelled');
    }

    /**
     * Delete order items.
     *
     * @param Customer $customer
     * @param array $orderItemIds
     * @return array
     * @throws Exception
     */
    public function deleteOrderItems(Customer $customer, array $orderItemIds): array
    {
        $items = OrderItem::where('customer_id', $customer->id)->whereIn('id', $orderItemIds)->get();

        if ($total = $items->count()) {
            foreach ($items as $item) {
                $this->deleteOrderItem($customer, $item);
            }
            return ['message' => $total . ($total === 1 ? ' item' : ' items') . ' deleted'];
        }

        throw new Exception('No items deleted');
    }

    /**
     * Show order item.
     *
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return OrderItemResource
     */
    public function showOrderItem(Customer $customer, OrderItem $order_item): OrderItemResource
    {
        return $this->showResource($order_item);
    }

    /**
     * Show order item summary.
     *
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return array
     */
    public function showOrderItemSummary(Customer $customer, OrderItem $order_item): array
    {
        $stats = Order::where('customer_id', $customer->id)
            ->where('order_item_id', $order_item->id)
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN fulfilled = 1 THEN 1 ELSE 0 END) as fulfilled_orders,
                SUM(CASE WHEN channel = "mobile" THEN 1 ELSE 0 END) as mobile_orders,
                SUM(CASE WHEN channel = "web" THEN 1 ELSE 0 END) as web_orders,
                SUM(total_amount) as total_revenue,
                SUM(total_items) as total_items,
                MIN(created_at) as first_order,
                MAX(updated_at) as last_order
            ')
            ->first();

        $totalOrders     = (int) ($stats->total_orders ?? 0);
        $fulfilledOrders = (int) ($stats->fulfilled_orders ?? 0);
        $mobileOrders    = (int) ($stats->mobile_orders ?? 0);
        $totalRevenue    = (int) ($stats->total_revenue ?? 0);
        $totalItems      = (int) ($stats->total_items ?? 0);

        $fulfilmentRate   = $totalOrders > 0 ? round(($fulfilledOrders / $totalOrders) * 100, 1) : 0;
        $mobilePct        = $totalOrders > 0 ? round(($mobileOrders / $totalOrders) * 100, 1) : 0;
        $webPct           = 100 - $mobilePct;
        $avgOrderValue    = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
        $avgItems         = $totalOrders > 0 ? round($totalItems / $totalOrders, 1) : 0;

        $firstOrder = $stats->first_order ? Carbon::parse($stats->first_order)->format('d M Y') : '—';
        $lastOrder  = $stats->last_order  ? Carbon::parse($stats->last_order)->format('d M Y')  : ($firstOrder !== '—' ? $firstOrder : '—');

        return [
            'total_orders'    => $totalOrders,
            'fulfilment_rate' => $fulfilmentRate,
            'first_order'     => $firstOrder,
            'last_order'      => $lastOrder,
            'avg_order_value' => $avgOrderValue,
            'avg_items'       => $avgItems,
            'mobile_percentage' => $mobilePct,
            'web_percentage'    => $webPct,
        ];
    }

    /**
     * Fulfill order item.
     *
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return array
     */
    public function fulfillOrderItem(Customer $customer, OrderItem $order_item): array
    {
        $alreadyFulfilled = $order_item->fulfilled_at !== null;

        if (!$alreadyFulfilled) {
            $order_item->update(['fulfilled_at' => now(), 'fulfilled' => true]);
        }

        return [
            'fulfilled' => true,
            'message'   => $alreadyFulfilled ? 'Item already fulfilled' : 'Item fulfilled',
        ];
    }

    /**
     * Cancel order item.
     *
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return array
     */
    public function cancelOrderItem(Customer $customer, OrderItem $order_item): array
    {
        $alreadyCancelled = $order_item->cancelled_at !== null;

        if (!$alreadyCancelled) {
            $order_item->update(['cancelled_at' => now()]);
        }

        return [
            'cancelled' => true,
            'message'   => $alreadyCancelled ? 'Item already cancelled' : 'Item cancelled',
        ];
    }

    /**
     * Delete order item.
     *
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return array
     */
    public function deleteOrderItem(Customer $customer, OrderItem $order_item): array
    {
        $deleted = $order_item->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Item deleted' : 'Item delete unsuccessful',
        ];
    }
}
