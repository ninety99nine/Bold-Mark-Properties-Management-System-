<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\DownloadSupplierStatementRequest;
use App\Http\Requests\Community\EmailSupplierStatementRequest;
use App\Http\Requests\Community\ShowSupplierStatementsRequest;
use App\Models\Community;
use App\Models\Supplier;
use App\Services\SupplierStatementService;
use Symfony\Component\HttpFoundation\Response;

class SupplierStatementController extends Controller
{
    protected SupplierStatementService $service;

    public function __construct(SupplierStatementService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the WeConnectU Supplier Statements table for a community — one row
     * per supplier with the ledger balance as at the "Date to".
     *
     * @param ShowSupplierStatementsRequest $request
     * @param Community $community
     * @return array
     */
    public function getStatements(ShowSupplierStatementsRequest $request, Community $community): array
    {
        return $this->service->getStatements($community, $request->validated());
    }

    /**
     * Stream the combined "View PDF" summary listing every supplier's reference
     * and balance (the WeConnectU "SuppliersStatement.pdf").
     *
     * @param ShowSupplierStatementsRequest $request
     * @param Community $community
     * @return Response
     */
    public function viewPdf(ShowSupplierStatementsRequest $request, Community $community): Response
    {
        return $this->service->viewCombinedPdf($community, $request->validated());
    }

    /**
     * Download a single supplier's statement PDF (the per-row Download icon).
     *
     * @param DownloadSupplierStatementRequest $request
     * @param Community $community
     * @param Supplier $supplier
     * @return Response
     */
    public function downloadStatement(DownloadSupplierStatementRequest $request, Community $community, Supplier $supplier): Response
    {
        return $this->service->downloadStatement($community, $supplier, $request->validated());
    }

    /**
     * E-mail a supplier's statement to the supplier's e-mail address.
     *
     * @param EmailSupplierStatementRequest $request
     * @param Community $community
     * @param Supplier $supplier
     * @return array
     */
    public function emailStatement(EmailSupplierStatementRequest $request, Community $community, Supplier $supplier): array
    {
        return $this->service->emailStatement($community, $supplier, $request->validated());
    }
}
