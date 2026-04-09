<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AgeAnalysisService;
use Illuminate\Http\Request;

class AgeAnalysisController extends Controller
{
    protected AgeAnalysisService $service;

    public function __construct(AgeAnalysisService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the age analysis report for the authenticated tenant.
     *
     * Query parameters (all optional):
     *   - estate_id:       Filter to a specific estate
     *   - charge_type_id:  Filter to a specific charge type
     *   - billed_to_type:  Filter to 'owner' or 'tenant'
     *
     * @param Request $request
     * @return array
     */
    public function getAgeAnalysis(Request $request): array
    {
        return $this->service->getAgeAnalysis($request->all());
    }

    /**
     * Export the age analysis report as a file download (CSV, Excel, or PDF).
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportAgeAnalysis(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->exportAgeAnalysis($request->all());
    }
}
