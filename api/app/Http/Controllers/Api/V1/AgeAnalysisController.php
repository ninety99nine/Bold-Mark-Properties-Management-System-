<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ShowAgeAnalysisRequest;
use App\Models\Community;
use App\Services\AgeAnalysisService;

class AgeAnalysisController extends Controller
{
    protected AgeAnalysisService $service;

    public function __construct(AgeAnalysisService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the WeConnectU-style Customer Age Analysis table for a community.
     *
     * @param ShowAgeAnalysisRequest $request
     * @param Community $community
     * @return array
     */
    public function getAgeAnalysis(ShowAgeAnalysisRequest $request, Community $community): array
    {
        return $this->service->getAgeAnalysis($community, $request->validated());
    }

    /**
     * Download the age analysis as a WeConnectU-faithful Excel workbook.
     *
     * @param ShowAgeAnalysisRequest $request
     * @param Community $community
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportAgeAnalysis(ShowAgeAnalysisRequest $request, Community $community): \Symfony\Component\HttpFoundation\Response
    {
        return $this->service->exportAgeAnalysis($community, $request->validated());
    }
}
