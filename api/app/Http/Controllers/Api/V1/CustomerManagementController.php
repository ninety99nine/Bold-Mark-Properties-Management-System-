<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomerManagementService;
use Illuminate\Http\Request;

class CustomerManagementController extends Controller
{
    protected CustomerManagementService $service;

    public function __construct(CustomerManagementService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of units with overdue invoices across all communities,
     * plus summary stats for the arrears page.
     */
    public function index(Request $request): array
    {
        return $this->service->showCustomers($request->all());
    }
}
