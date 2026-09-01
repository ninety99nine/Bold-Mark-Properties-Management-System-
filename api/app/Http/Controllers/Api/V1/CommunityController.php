<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\CommunityService;
use App\Services\CommunityDashboardService;
use App\Http\Resources\CommunityResource;
use App\Http\Resources\CommunityResources;
use App\Http\Requests\Community\ShowCommunityRequest;
use App\Http\Requests\Community\ShowCommunityDashboardRequest;
use App\Http\Requests\Community\ShowCommunitiesRequest;
use App\Http\Requests\Community\ShowCommunitySummaryRequest;
use App\Http\Requests\Community\CreateCommunityRequest;
use App\Http\Requests\Community\UpdateCommunityRequest;
use App\Http\Requests\Community\DeleteCommunityRequest;
use App\Http\Requests\Community\DeleteCommunitiesRequest;

class CommunityController extends Controller
{
    protected CommunityService $service;

    public function __construct(CommunityService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of communities.
     *
     * @param ShowCommunitiesRequest $request
     * @return CommunityResources
     */
    public function showCommunities(ShowCommunitiesRequest $request): CommunityResources
    {
        return $this->service->showCommunities($request->validated());
    }

    /**
     * Return aggregate summary statistics across all communities.
     *
     * @param ShowCommunitySummaryRequest $request
     * @return array
     */
    public function showCommunitiesSummary(ShowCommunitySummaryRequest $request): array
    {
        return $this->service->showCommunitiesSummary($request->validated());
    }

    /**
     * Create a new community.
     *
     * @param CreateCommunityRequest $request
     * @return array
     */
    public function createCommunity(CreateCommunityRequest $request): array
    {
        return $this->service->createCommunity($request->validated());
    }

    /**
     * Bulk delete communities.
     *
     * @param DeleteCommunitiesRequest $request
     * @return array
     */
    public function deleteCommunities(DeleteCommunitiesRequest $request): array
    {
        return $this->service->deleteCommunities($request->input('community_ids', []));
    }

    /**
     * Return a single community with computed stats for the detail page.
     *
     * @param ShowCommunityRequest $request
     * @param Community            $community
     * @return \Illuminate\Http\JsonResponse
     */
    public function showCommunity(ShowCommunityRequest $request, Community $community): \Illuminate\Http\JsonResponse
    {
        return $this->service->showCommunity($community);
    }

    /**
     * Return the WeConnectU-style community dashboard payload.
     *
     * @param ShowCommunityDashboardRequest $request
     * @param Community                     $community
     * @return array
     */
    public function dashboard(ShowCommunityDashboardRequest $request, Community $community): array
    {
        return app(CommunityDashboardService::class)
            ->dashboard($community, $request->validated('financial_year'));
    }

    /**
     * Return occupant analytics for an community.
     *
     * @param Community $community
     * @return \Illuminate\Http\JsonResponse
     */
    public function occupantAnalytics(Community $community): \Illuminate\Http\JsonResponse
    {
        return $this->service->showOccupantAnalytics($community);
    }

    /**
     * Update an community.
     *
     * @param UpdateCommunityRequest $request
     * @param Community              $community
     * @return array
     */
    public function updateCommunity(UpdateCommunityRequest $request, Community $community): array
    {
        return $this->service->updateCommunity($community, $request->validated());
    }

    /**
     * Delete a single community.
     *
     * @param DeleteCommunityRequest $request
     * @param Community              $community
     * @return array
     */
    public function deleteCommunity(DeleteCommunityRequest $request, Community $community): array
    {
        return $this->service->deleteCommunity($community);
    }
}
