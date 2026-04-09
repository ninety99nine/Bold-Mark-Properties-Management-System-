<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OrderItem;
use App\Services\OrderItemService;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderItemResources;
use App\Http\Requests\OrderItem\ShowOrderItemRequest;
use App\Http\Requests\OrderItem\ShowOrderItemsRequest;
use App\Http\Requests\OrderItem\CancelOrderItemRequest;
use App\Http\Requests\OrderItem\CancelOrderItemsRequest;
use App\Http\Requests\OrderItem\CreateOrderItemRequest;
use App\Http\Requests\OrderItem\DeleteOrderItemRequest;
use App\Http\Requests\OrderItem\DeleteOrderItemsRequest;
use App\Http\Requests\OrderItem\FulfillOrderItemRequest;
use App\Http\Requests\OrderItem\FulfillOrderItemsRequest;
use App\Http\Requests\OrderItem\ShowOrderItemSummaryRequest;
use App\Http\Requests\OrderItem\ShowOrderItemsSummaryRequest;

class OrderItemController extends Controller
{
    /**
     * @var OrderItemService
     */
    protected $service;

    /**
     * OrderItemController constructor.
     *
     * @param OrderItemService $service
     */
    public function __construct(OrderItemService $service)
    {
        $this->service = $service;
    }

    /**
     * Show order items.
     *
     * @param ShowOrderItemsRequest $request
     * @param Customer $customer
     * @return OrderItemResources|array
     */
    public function showOrderItems(ShowOrderItemsRequest $request, Customer $customer): OrderItemResources|array
    {
        return $this->service->showOrderItems($customer, $request->validated());
    }

    /**
     * Show order items summary.
     *
     * @param ShowOrderItemsSummaryRequest $request
     * @param Customer $customer
     * @return array
     */
    public function showOrderItemsSummary(ShowOrderItemsSummaryRequest $request, Customer $customer): array
    {
        return $this->service->showOrderItemsSummary($customer, $request->validated());
    }

    /**
     * Fulfill order items.
     *
     * @param FulfillOrderItemsRequest $request
     * @param Customer $customer
     * @return array
     */
    public function fulfillOrderItems(FulfillOrderItemsRequest $request, Customer $customer): array
    {
        $orderItemIds = $request->input('order_item_ids', []);
        return $this->service->fulfillOrderItems($customer, $orderItemIds);
    }

    /**
     * Cancel order items.
     *
     * @param CancelOrderItemsRequest $request
     * @param Customer $customer
     * @return array
     */
    public function cancelOrderItems(CancelOrderItemsRequest $request, Customer $customer): array
    {
        $orderItemIds = $request->input('order_item_ids', []);
        return $this->service->cancelOrderItems($customer, $orderItemIds);
    }

    /**
     * Delete order items.
     *
     * @param DeleteOrderItemsRequest $request
     * @param Customer $customer
     * @return array
     */
    public function deleteOrderItems(DeleteOrderItemsRequest $request, Customer $customer): array
    {
        $orderItemIds = $request->input('order_item_ids', []);
        return $this->service->deleteOrderItems($customer, $orderItemIds);
    }

    /**
     * Show order item.
     *
     * @param ShowOrderItemRequest $request
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return OrderItemResource
     */
    public function showOrderItem(ShowOrderItemRequest $request, Customer $customer, OrderItem $order_item): OrderItemResource
    {
        return $this->service->showOrderItem($customer, $order_item);
    }

    /**
     * Show order item summary.
     *
     * @param ShowOrderItemSummaryRequest $request
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return array
     */
    public function showOrderItemSummary(ShowOrderItemSummaryRequest $request, Customer $customer, OrderItem $order_item): array
    {
        return $this->service->showOrderItemSummary($customer, $order_item);
    }

    /**
     * Fulfill order item.
     *
     * @param FulfillOrderItemRequest $request
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return array
     */
    public function fulfillOrderItem(FulfillOrderItemRequest $request, Customer $customer, OrderItem $order_item): array
    {
        return $this->service->fulfillOrderItem($customer, $order_item);
    }

    /**
     * Cancel order item.
     *
     * @param CancelOrderItemRequest $request
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return array
     */
    public function cancelOrderItem(CancelOrderItemRequest $request, Customer $customer, OrderItem $order_item): array
    {
        return $this->service->cancelOrderItem($customer, $order_item);
    }

    /**
     * Delete order item.
     *
     * @param DeleteOrderItemRequest $request
     * @param Customer $customer
     * @param OrderItem $order_item
     * @return array
     */
    public function deleteOrderItem(DeleteOrderItemRequest $request, Customer $customer, OrderItem $order_item): array
    {
        return $this->service->deleteOrderItem($customer, $order_item);
    }
}
