<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AllocationRule\CreateAllocationRuleRequest;
use App\Http\Requests\AllocationRule\DeleteAllocationRuleRequest;
use App\Http\Requests\AllocationRule\ShowAllocationRuleRequest;
use App\Http\Requests\AllocationRule\ShowAllocationRulesRequest;
use App\Http\Requests\AllocationRule\UpdateAllocationRuleRequest;
use App\Http\Resources\AllocationRuleResource;
use App\Models\AllocationRule;
use App\Models\Community;
use App\Services\AllocationRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AllocationRuleController extends Controller
{
    protected AllocationRuleService $service;

    public function __construct(AllocationRuleService $service)
    {
        $this->service = $service;
    }

    /**
     * Paginated list of allocation rules for a community.
     *
     * @param ShowAllocationRulesRequest $request
     * @param Community                  $community
     * @return ResourceCollection
     */
    public function showAllocationRules(ShowAllocationRulesRequest $request, Community $community): ResourceCollection
    {
        return $this->service->showAllocationRules(['community_id' => $community->id]);
    }

    /**
     * Create an allocation rule for a community.
     *
     * @param CreateAllocationRuleRequest $request
     * @param Community                   $community
     * @return JsonResponse
     */
    public function createAllocationRule(CreateAllocationRuleRequest $request, Community $community): JsonResponse
    {
        $data = array_merge($request->validated(), ['community_id' => $community->id]);

        return response()->json($this->service->createAllocationRule($data), 201);
    }

    /**
     * Show a single allocation rule.
     *
     * @param ShowAllocationRuleRequest $request
     * @param Community                 $community
     * @param AllocationRule            $allocationRule
     * @return AllocationRuleResource
     */
    public function showAllocationRule(ShowAllocationRuleRequest $request, Community $community, AllocationRule $allocationRule): AllocationRuleResource
    {
        return $this->service->showAllocationRule($allocationRule);
    }

    /**
     * Update an allocation rule.
     *
     * @param UpdateAllocationRuleRequest $request
     * @param Community                   $community
     * @param AllocationRule              $allocationRule
     * @return JsonResponse
     */
    public function updateAllocationRule(UpdateAllocationRuleRequest $request, Community $community, AllocationRule $allocationRule): JsonResponse
    {
        return response()->json($this->service->updateAllocationRule($allocationRule, $request->validated()));
    }

    /**
     * Delete an allocation rule.
     *
     * @param DeleteAllocationRuleRequest $request
     * @param Community                   $community
     * @param AllocationRule              $allocationRule
     * @return JsonResponse
     */
    public function deleteAllocationRule(DeleteAllocationRuleRequest $request, Community $community, AllocationRule $allocationRule): JsonResponse
    {
        return response()->json($this->service->deleteAllocationRule($allocationRule));
    }
}
