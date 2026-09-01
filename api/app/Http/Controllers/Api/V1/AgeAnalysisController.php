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
     * Return the age analysis report for the authenticated occupant.
     *
     * Query parameters (all optional):
     *   - community_id:       Filter to a specific community
     *   - ledger_id:  Filter to a specific ledger
     *   - billed_to_type:  Filter to 'owner' or 'occupant'
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

    /**
     * Bulk "Send Notices": advance the collection status of arrears customers
     * in scope and log a note. Honours the same filters as the report.
     *
     * @param Request $request
     * @return array
     */
    public function sendNotices(Request $request): array
    {
        return $this->service->sendNotices($request->all());
    }
}
