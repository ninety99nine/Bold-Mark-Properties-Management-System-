<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ShowSupplierAgeAnalysisRequest;
use App\Models\Community;
use App\Models\Supplier;
use App\Services\SupplierAgeAnalysisService;
use Symfony\Component\HttpFoundation\Response;

class SupplierAgeAnalysisController extends Controller
{
    /**
     * @param SupplierAgeAnalysisService $service
     */
    public function __construct(protected SupplierAgeAnalysisService $service)
    {
    }

    /**
     * Return the supplier age-analysis table for a community.
     *
     * @param ShowSupplierAgeAnalysisRequest $request
     * @param Community $community
     * @return array
     */
    public function getAgeAnalysis(ShowSupplierAgeAnalysisRequest $request, Community $community): array
    {
        return $this->service->getAgeAnalysis($community, $request->validated());
    }

    /**
     * Return the Detailed-Ledger drill-down for a single supplier.
     *
     * @param ShowSupplierAgeAnalysisRequest $request
     * @param Community $community
     * @param Supplier $supplier
     * @return array
     */
    public function supplierLedger(ShowSupplierAgeAnalysisRequest $request, Community $community, Supplier $supplier): array
    {
        return $this->service->supplierLedger($community, $supplier, $request->validated());
    }

    /**
     * Download the supplier age analysis as an Excel workbook.
     *
     * @param ShowSupplierAgeAnalysisRequest $request
     * @param Community $community
     * @return Response
     */
    public function exportAgeAnalysis(ShowSupplierAgeAnalysisRequest $request, Community $community): Response
    {
        return $this->service->exportAgeAnalysis($community, $request->validated());
    }
}
