<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderResources;
use App\Http\Requests\Order\ShowOrderRequest;
use App\Http\Requests\Order\ShowOrdersRequest;
use App\Http\Requests\Order\ShowOrdersSummaryRequest;

class OrderController extends Controller
{
    /**
     * @var OrderService
     */
    protected $service;

    /**
     * OrderController constructor.
     *
     * @param OrderService $service
     */
    public function __construct(OrderService $service)
    {
        $this->service = $service;
    }

    /**
     * Show orders.
     *
     * @param ShowOrdersRequest $request
     * @param Customer $customer
     * @return OrderResources|array
     */
    public function showOrders(ShowOrdersRequest $request, Customer $customer): OrderResources|array
    {
        return $this->service->showOrders($customer, $request->validated());
    }

    /**
     * Show orders summary.
     *
     * @param ShowOrdersSummaryRequest $request
     * @param Customer $customer
     * @return array
     */
    public function showOrdersSummary(ShowOrdersSummaryRequest $request, Customer $customer): array
    {
        return $this->service->showOrdersSummary($customer, $request->validated());
    }

    /**
     * Show order.
     *
     * @param ShowOrderRequest $request
     * @param Customer $customer
     * @param Order $order
     * @return OrderResource
     */
    public function showOrder(ShowOrderRequest $request, Customer $customer, Order $order): OrderResource
    {
        return $this->service->showOrder($customer, $order);
    }
}
