<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ArrearsService;
use Illuminate\Http\Request;

class ArrearsController extends Controller
{
    protected ArrearsService $service;

    public function __construct(ArrearsService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of units with overdue invoices across all estates,
     * plus summary stats for the arrears page.
     */
    public function index(Request $request): array
    {
        return $this->service->showArrears($request->all());
    }
}
