<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\CustomerStatusService;
use App\Http\Requests\CustomerStatus\ShowCustomerStatusesRequest;
use App\Http\Requests\CustomerStatus\ApplyCustomerStatusesRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerStatusController extends Controller
{
    /**
     * @var CustomerStatusService
     */
    protected $service;

    /**
     * CustomerStatusController constructor.
     *
     * @param CustomerStatusService $service
     */
    public function __construct(CustomerStatusService $service)
    {
        $this->service = $service;
    }

    /**
     * Show the customer-status grid for a community.
     *
     * @param ShowCustomerStatusesRequest $request
     * @param Community $community
     * @return array
     */
    public function showCustomerStatuses(ShowCustomerStatusesRequest $request, Community $community): array
    {
        return $this->service->showCustomerStatuses($community, $request->query());
    }

    /**
     * Apply a collection status to the selected customers.
     *
     * @param ApplyCustomerStatusesRequest $request
     * @param Community $community
     * @return array
     */
    public function applyStatuses(ApplyCustomerStatusesRequest $request, Community $community): array
    {
        return $this->service->applyStatuses($community, $request->validated());
    }

    /**
     * Download the customer-statuses report as an xlsx.
     *
     * @param ShowCustomerStatusesRequest $request
     * @param Community $community
     * @return StreamedResponse
     */
    public function downloadStatuses(ShowCustomerStatusesRequest $request, Community $community): StreamedResponse
    {
        return $this->service->downloadStatuses($community, $request->query());
    }

    /**
     * Show the manual collection-status history for a community.
     *
     * @param ShowCustomerStatusesRequest $request
     * @param Community $community
     * @return array
     */
    public function showStatusHistory(ShowCustomerStatusesRequest $request, Community $community): array
    {
        return $this->service->showStatusHistory($community, $request->query());
    }

    /**
     * Show automatic collection-status changes for a community.
     *
     * @param ShowCustomerStatusesRequest $request
     * @param Community $community
     * @return array
     */
    public function showAutomaticStatusChanges(ShowCustomerStatusesRequest $request, Community $community): array
    {
        return $this->service->showAutomaticStatusChanges($community, $request->query());
    }
}
