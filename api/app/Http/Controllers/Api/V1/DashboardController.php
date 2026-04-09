<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected DashboardService $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    /**
     * Return all summary data needed to render the dashboard.
     *
     * @param Request $request
     * @return array
     */
    public function getDashboardSummary(Request $request): array
    {
        return $this->service->getDashboardSummary();
    }
}
