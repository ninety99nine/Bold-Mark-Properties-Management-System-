<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ShowCustomerStatementsRequest;
use App\Models\Community;
use App\Services\CustomerStatementService;

class CustomerStatementController extends Controller
{
    protected CustomerStatementService $service;

    public function __construct(CustomerStatementService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the WeConnectU Customer Statements table for a community — one row
     * per customer with the balance as at the "Date to".
     *
     * @param ShowCustomerStatementsRequest $request
     * @param Community $community
     * @return array
     */
    public function getStatements(ShowCustomerStatementsRequest $request, Community $community): array
    {
        return $this->service->getStatements($community, $request->validated());
    }

    /**
     * Stream a single combined PDF of every listed customer's statement
     * (the WeConnectU "View PDF" button).
     *
     * @param ShowCustomerStatementsRequest $request
     * @param Community $community
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewPdf(ShowCustomerStatementsRequest $request, Community $community): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->viewCombinedPdf($community, $request->validated());
    }
}
