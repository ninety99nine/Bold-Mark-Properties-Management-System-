<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RiskRule;
use App\Services\RiskRuleService;
use App\Http\Resources\RiskRuleResources;
use Illuminate\Http\Request;
use App\Http\Requests\RiskRule\ShowRiskRulesRequest;
use App\Http\Requests\RiskRule\CreateRiskRuleRequest;
use App\Http\Requests\RiskRule\UpdateRiskRuleRequest;
use App\Http\Requests\RiskRule\DeleteRiskRuleRequest;

class RiskRuleController extends Controller
{
    protected RiskRuleService $service;

    public function __construct(RiskRuleService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of risk rules.
     *
     * @param ShowRiskRulesRequest $request
     * @return RiskRuleResources
     */
    public function showRiskRules(ShowRiskRulesRequest $request): RiskRuleResources
    {
        return $this->service->showRiskRules($request->validated());
    }

    /**
     * Create a new risk rule.
     *
     * @param CreateRiskRuleRequest $request
     * @return array
     */
    public function createRiskRule(CreateRiskRuleRequest $request): array
    {
        return $this->service->createRiskRule($request->validated());
    }

    /**
     * Update an existing risk rule.
     *
     * @param UpdateRiskRuleRequest $request
     * @param RiskRule              $riskRule
     * @return array
     */
    public function updateRiskRule(UpdateRiskRuleRequest $request, RiskRule $riskRule): array
    {
        return $this->service->updateRiskRule($riskRule, $request->validated());
    }

    /**
     * Delete a risk rule.
     *
     * @param DeleteRiskRuleRequest $request
     * @param RiskRule              $riskRule
     * @return array
     */
    public function deleteRiskRule(DeleteRiskRuleRequest $request, RiskRule $riskRule): array
    {
        return $this->service->deleteRiskRule($riskRule);
    }

    /**
     * Evaluate all active risk rules and return flagged units.
     *
     * @return array
     */
    public function evaluate(): array
    {
        return $this->service->evaluateRules();
    }

    /**
     * Reorder risk rules.
     *
     * @param Request $request
     * @return array
     */
    public function reorder(Request $request): array
    {
        $request->validate([
            'rule_ids'   => ['required', 'array', 'min:1'],
            'rule_ids.*' => ['required', 'uuid'],
        ]);

        return $this->service->reorderRules($request->input('rule_ids'));
    }
}
