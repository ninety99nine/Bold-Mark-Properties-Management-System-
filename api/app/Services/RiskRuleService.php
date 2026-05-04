<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\Invoice;
use App\Models\RiskRule;
use App\Enums\InvoiceStatus;
use App\Http\Resources\RiskRuleResource;
use App\Http\Resources\RiskRuleResources;
use Illuminate\Support\Facades\Auth;

class RiskRuleService extends BaseService
{
    /**
     * Return a paginated list of risk rules for the authenticated tenant.
     *
     * @param array $data
     * @return RiskRuleResources
     */
    public function showRiskRules(array $data): RiskRuleResources
    {
        $user  = Auth::user();
        $query = RiskRule::where('organization_id', $user->organization_id);

        if (isset($data['is_active'])) {
            $isActive = $data['is_active'] === 'true' || $data['is_active'] === true || $data['is_active'] === 1;
            $query->where('is_active', $isActive);
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a new risk rule for the authenticated tenant.
     *
     * @param array $data
     * @return array
     */
    public function createRiskRule(array $data): array
    {
        $user = Auth::user();

        // Place new rule at the end
        $maxSort = RiskRule::where('organization_id', $user->organization_id)->max('sort_order') ?? -1;

        $riskRule = RiskRule::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'severity'    => $data['severity'],
            'conditions'  => $data['conditions'],
            'is_active'   => $data['is_active'] ?? true,
            'sort_order'  => $maxSort + 1,
            'organization_id'   => $user->organization_id,
        ]);

        return $this->showCreatedResource($riskRule);
    }

    /**
     * Reorder risk rules by updating sort_order for each rule.
     *
     * @param array $ruleIds  Ordered array of rule UUIDs
     * @return array
     */
    public function reorderRules(array $ruleIds): array
    {
        $user = Auth::user();

        foreach ($ruleIds as $index => $id) {
            RiskRule::where('id', $id)
                ->where('organization_id', $user->organization_id)
                ->update(['sort_order' => $index]);
        }

        return ['message' => 'Rules reordered'];
    }

    /**
     * Update an existing risk rule.
     *
     * @param RiskRule $riskRule
     * @param array    $data
     * @return array
     */
    public function updateRiskRule(RiskRule $riskRule, array $data): array
    {
        $riskRule->update($data);

        return $this->showUpdatedResource($riskRule);
    }

    /**
     * Delete a risk rule.
     *
     * @param RiskRule $riskRule
     * @return array
     */
    public function deleteRiskRule(RiskRule $riskRule): array
    {
        $riskRule->delete();

        return [
            'deleted' => true,
            'message' => 'Risk rule deleted',
        ];
    }

    /**
     * Evaluate all active risk rules against units with overdue invoices.
     *
     * For each unit in arrears, computes four metrics (overdue_amount,
     * overdue_invoice_count, days_overdue, arrears_rate) and checks them
     * against each active rule's conditions (AND within a rule, OR across rules).
     *
     * @return array
     */
    public function evaluateRules(): array
    {
        $user     = Auth::user();
        $tenantId = $user->organization_id;

        // Fetch ALL rules for this tenant (both active and inactive for display)
        $allRules = RiskRule::where('organization_id', $tenantId)->orderBy('sort_order')->get();

        // Only active rules are used for evaluation
        $activeRules = $allRules->filter(fn($r) => $r->is_active);

        if ($allRules->isEmpty()) {
            return [
                'rules'         => [],
                'flagged_units' => [],
                'total_flagged' => 0,
            ];
        }

        // Query all units in arrears with computed metrics
        $units = Unit::where('units.organization_id', $tenantId)
            ->whereHas('invoices', fn($q) => $q->where('status', InvoiceStatus::OVERDUE))
            ->with(['owner:id,full_name,email,unit_id', 'estate:id,name,type'])
            ->addSelect([
                'units.*',

                // Total overdue amount (reuses ArrearsService pattern)
                'overdue_amount' => Invoice::selectRaw(
                    "COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0)"
                )
                    ->whereColumn('invoices.unit_id', 'units.id')
                    ->where('invoices.status', InvoiceStatus::OVERDUE),

                // Count of overdue invoices
                'overdue_invoice_count' => Invoice::selectRaw('COUNT(*)')
                    ->whereColumn('invoices.unit_id', 'units.id')
                    ->where('invoices.status', InvoiceStatus::OVERDUE),

                // Days since oldest overdue invoice
                'days_overdue' => Invoice::selectRaw("COALESCE(EXTRACT(DAY FROM NOW() - MIN(invoices.due_date)), 0)")
                    ->whereColumn('invoices.unit_id', 'units.id')
                    ->where('invoices.status', InvoiceStatus::OVERDUE),

                // Total invoice count (for arrears_rate)
                'total_invoice_count' => Invoice::selectRaw('COUNT(*)')
                    ->whereColumn('invoices.unit_id', 'units.id'),
            ])
            ->get();

        // Evaluate ALL rules (active + inactive) to compute flagged_count per rule.
        // Only active rules contribute to the flagged_units list shown to the user.
        $ruleMatchCounts = [];   // rule_id => count of matching units
        $flaggedUnits    = [];

        foreach ($units as $unit) {
            $overdueCount = (int) $unit->overdue_invoice_count;
            $totalCount   = (int) $unit->total_invoice_count;
            $arrearsRate  = $totalCount > 0 ? ($overdueCount / $totalCount) * 100 : 0;

            $unitMetrics = [
                'overdue_amount'        => (float) $unit->overdue_amount,
                'overdue_invoice_count' => $overdueCount,
                'days_overdue'          => (int) max(0, $unit->days_overdue),
                'arrears_rate'          => round($arrearsRate, 1),
            ];

            $activeMatchedRules = [];

            // Check every rule (active + inactive) for flagged_count
            foreach ($allRules as $rule) {
                $allConditionsMet = true;

                foreach ($rule->conditions as $condition) {
                    $metric    = $unitMetrics[$condition['type']] ?? 0;
                    $threshold = (float) $condition['value'];

                    if ($metric < $threshold) {
                        $allConditionsMet = false;
                        break;
                    }
                }

                if ($allConditionsMet) {
                    // Count the match for this rule's flagged_count (active or not)
                    $ruleMatchCounts[$rule->id] = ($ruleMatchCounts[$rule->id] ?? 0) + 1;

                    // Only active rules contribute to the visible flagged units list
                    if ($rule->is_active) {
                        $activeMatchedRules[] = [
                            'id'       => $rule->id,
                            'name'     => $rule->name,
                            'severity' => $rule->severity instanceof \BackedEnum ? $rule->severity->value : $rule->severity,
                        ];
                    }
                }
            }

            // Only add to flagged_units if matched by at least one active rule
            if (!empty($activeMatchedRules)) {
                $highestSeverity = collect($activeMatchedRules)->contains(fn($r) => $r['severity'] === 'critical')
                    ? 'critical'
                    : 'warning';

                $flaggedUnits[] = [
                    'unit_id'        => $unit->id,
                    'unit_number'    => $unit->unit_number,
                    'estate_id'     => $unit->estate_id,
                    'estate_name'    => $unit->estate?->name,
                    'owner_name'     => $unit->owner?->full_name,
                    'owner_email'    => $unit->owner?->email,
                    'overdue_amount' => $unitMetrics['overdue_amount'],
                    'days_overdue'   => $unitMetrics['days_overdue'],
                    'overdue_count'  => $unitMetrics['overdue_invoice_count'],
                    'arrears_rate'   => $unitMetrics['arrears_rate'],
                    'matched_rules'  => $activeMatchedRules,
                    'severity'       => $highestSeverity,
                ];
            }
        }

        // Sort: critical first, then by overdue_amount descending
        usort($flaggedUnits, function ($a, $b) {
            if ($a['severity'] !== $b['severity']) {
                return $a['severity'] === 'critical' ? -1 : 1;
            }
            return $b['overdue_amount'] <=> $a['overdue_amount'];
        });

        // Build rules response — flagged_count reflects matches regardless of active state
        $rulesResponse = $allRules->map(function ($rule) use ($ruleMatchCounts) {
            $resource = (new RiskRuleResource($rule))->resolve();
            $resource['flagged_count'] = $ruleMatchCounts[$rule->id] ?? 0;
            return $resource;
        });

        return [
            'rules'         => $rulesResponse,
            'flagged_units' => $flaggedUnits,
            'total_flagged' => count($flaggedUnits),
        ];
    }
}
