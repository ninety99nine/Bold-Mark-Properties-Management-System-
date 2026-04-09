<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Estate;
use App\Services\EstateService;
use App\Http\Resources\EstateResource;
use App\Http\Resources\EstateResources;
use App\Http\Requests\Estate\ShowEstateRequest;
use App\Http\Requests\Estate\ShowEstatesRequest;
use App\Http\Requests\Estate\ShowEstateSummaryRequest;
use App\Http\Requests\Estate\CreateEstateRequest;
use App\Http\Requests\Estate\UpdateEstateRequest;
use App\Http\Requests\Estate\DeleteEstateRequest;
use App\Http\Requests\Estate\DeleteEstatesRequest;

class EstateController extends Controller
{
    protected EstateService $service;

    public function __construct(EstateService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of estates.
     *
     * @param ShowEstatesRequest $request
     * @return EstateResources
     */
    public function showEstates(ShowEstatesRequest $request): EstateResources
    {
        return $this->service->showEstates($request->validated());
    }

    /**
     * Return aggregate summary statistics across all estates.
     *
     * @param ShowEstateSummaryRequest $request
     * @return array
     */
    public function showEstatesSummary(ShowEstateSummaryRequest $request): array
    {
        return $this->service->showEstatesSummary($request->validated());
    }

    /**
     * Create a new estate.
     *
     * @param CreateEstateRequest $request
     * @return array
     */
    public function createEstate(CreateEstateRequest $request): array
    {
        return $this->service->createEstate($request->validated());
    }

    /**
     * Bulk delete estates.
     *
     * @param DeleteEstatesRequest $request
     * @return array
     */
    public function deleteEstates(DeleteEstatesRequest $request): array
    {
        return $this->service->deleteEstates($request->input('estate_ids', []));
    }

    /**
     * Return a single estate with computed stats for the detail page.
     *
     * @param ShowEstateRequest $request
     * @param Estate            $estate
     * @return \Illuminate\Http\JsonResponse
     */
    public function showEstate(ShowEstateRequest $request, Estate $estate): \Illuminate\Http\JsonResponse
    {
        return $this->service->showEstate($estate);
    }

    /**
     * Return tenant analytics for an estate.
     *
     * @param Estate $estate
     * @return \Illuminate\Http\JsonResponse
     */
    public function tenantAnalytics(Estate $estate): \Illuminate\Http\JsonResponse
    {
        return $this->service->showTenantAnalytics($estate);
    }

    /**
     * Update an estate.
     *
     * @param UpdateEstateRequest $request
     * @param Estate              $estate
     * @return array
     */
    public function updateEstate(UpdateEstateRequest $request, Estate $estate): array
    {
        return $this->service->updateEstate($estate, $request->validated());
    }

    /**
     * Delete a single estate.
     *
     * @param DeleteEstateRequest $request
     * @param Estate              $estate
     * @return array
     */
    public function deleteEstate(DeleteEstateRequest $request, Estate $estate): array
    {
        return $this->service->deleteEstate($estate);
    }
}
