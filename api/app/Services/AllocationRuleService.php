<?php

namespace App\Services;

use App\Enums\CashbookEntryType;
use App\Enums\JournalLineType;
use App\Http\Resources\AllocationRuleResource;
use App\Http\Resources\AllocationRuleResources;
use App\Models\AllocationRule;
use App\Models\CashbookEntry;
use App\Models\Community;
use Exception;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Auth;

class AllocationRuleService extends BaseService
{
    protected string $resourceClass           = AllocationRuleResource::class;
    protected string $resourceCollectionClass = AllocationRuleResources::class;

    public function __construct(private readonly AllocationPostingService $posting)
    {
        parent::__construct();
    }

    /**
     * Paginated, org- and community-scoped list of allocation rules.
     *
     * @param array $data
     * @return AllocationRuleResources|array
     */
    public function showAllocationRules(array $data): AllocationRuleResources|array
    {
        $user = Auth::user();

        $query = AllocationRule::where('organization_id', $user->organization_id)
            ->where('community_id', $data['community_id']);

        if (!request()->has('_sort')) {
            $query = $query->orderBy('sort_order')->orderBy('created_at');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create an allocation rule for a community.
     *
     * @param array $data
     * @return array
     */
    public function createAllocationRule(array $data): array
    {
        $user = Auth::user();

        $rule = AllocationRule::create([
            'description_starts_with' => $data['description_starts_with'] ?? null,
            'description_contains'    => $data['description_contains'] ?? null,
            'ledger_type'             => $data['ledger_type'],
            'ledger_id'               => $data['ledger_id'] ?? null,
            'unit_id'                 => $data['unit_id'] ?? null,
            'supplier_id'             => $data['supplier_id'] ?? null,
            'vat_type'                => $data['vat_type'] ?? null,
            'remarks'                 => $data['remarks'] ?? null,
            'apply_to_positive'       => $data['apply_to_positive'] ?? true,
            'apply_to_negative'       => $data['apply_to_negative'] ?? true,
            'bank_account_ids'        => $data['bank_account_ids'] ?? null,
            'sort_order'              => $data['sort_order'] ?? $this->nextSortOrder($data['community_id']),
            'community_id'            => $data['community_id'],
            'organization_id'         => $user->organization_id,
        ]);

        return $this->showCreatedResource($rule);
    }

    /**
     * Show a single allocation rule.
     *
     * @param AllocationRule $allocationRule
     * @return AllocationRuleResource
     */
    public function showAllocationRule(AllocationRule $allocationRule): AllocationRuleResource
    {
        return $this->showResource($allocationRule);
    }

    /**
     * Update an allocation rule.
     *
     * @param AllocationRule $allocationRule
     * @param array          $data
     * @return array
     */
    public function updateAllocationRule(AllocationRule $allocationRule, array $data): array
    {
        $allocationRule->update($data);

        return $this->showUpdatedResource($allocationRule->fresh());
    }

    /**
     * Delete an allocation rule.
     *
     * @param AllocationRule $allocationRule
     * @return array
     */
    public function deleteAllocationRule(AllocationRule $allocationRule): array
    {
        $deleted = $allocationRule->delete();

        return [
            'deleted' => (bool) $deleted,
            'message' => $deleted ? 'Allocation rule deleted' : 'Allocation rule delete unsuccessful',
        ];
    }

    // -------------------------------------------------------------------------
    // Matching engine
    // -------------------------------------------------------------------------

    /**
     * Determine whether a rule matches a cashbook entry.
     *
     * @param AllocationRule $rule
     * @param CashbookEntry  $entry
     * @return bool
     */
    public function matches(AllocationRule $rule, CashbookEntry $entry): bool
    {
        $startsWith = trim((string) $rule->description_starts_with);
        $contains   = trim((string) $rule->description_contains);

        // A rule with no text criteria never matches.
        if ($startsWith === '' && $contains === '') {
            return false;
        }

        $description = strtolower((string) $entry->description);

        if ($startsWith !== '' && !str_starts_with($description, strtolower($startsWith))) {
            return false;
        }

        if ($contains !== '' && !str_contains($description, strtolower($contains))) {
            return false;
        }

        // Amount sign: credit ⇒ positive, debit ⇒ negative.
        $isPositive = $entry->type === CashbookEntryType::CREDIT;

        if ($isPositive && !$rule->apply_to_positive) {
            return false;
        }

        if (!$isPositive && !$rule->apply_to_negative) {
            return false;
        }

        // Bank account filter (null/empty = all cashbooks).
        $bankAccountIds = $rule->bank_account_ids ?? [];

        if (!empty($bankAccountIds) && !in_array($entry->bank_account_id, $bankAccountIds, true)) {
            return false;
        }

        return true;
    }

    /**
     * Apply the first matching rule to an unallocated entry.
     *
     * @param CashbookEntry $entry
     * @return bool  True when the entry was allocated.
     * @throws Exception
     */
    public function applyRulesToEntry(CashbookEntry $entry): bool
    {
        if ($entry->is_allocated) {
            return false;
        }

        $rule = $this->firstMatchingRule($entry);

        if (!$rule) {
            return false;
        }

        $this->posting->post($entry, $this->allocationFromRule($rule));

        return true;
    }

    /**
     * Apply allocation rules across every unallocated entry in a community.
     *
     * @param string      $communityId
     * @param string|null $bankAccountId
     * @return int  Number of entries allocated.
     * @throws Exception
     */
    public function applyRulesForCommunity(string $communityId, ?string $bankAccountId = null): int
    {
        $query = CashbookEntry::where('community_id', $communityId)->unallocated();

        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        $allocated = 0;

        $query->get()->each(function (CashbookEntry $entry) use (&$allocated) {
            if ($this->applyRulesToEntry($entry)) {
                $allocated++;
            }
        });

        return $allocated;
    }

    /**
     * The grey hint line shown under a cashbook row: the first rule (allocated or
     * not) that matches the entry, and the account it would allocate to.
     *
     * @param CashbookEntry $entry
     * @return array{starts_with: ?string, contains: ?string, account_label: ?string}|null
     */
    public function ruleHintFor(CashbookEntry $entry): ?array
    {
        $rule = $this->firstMatchingRule($entry);

        if (!$rule) {
            return null;
        }

        return [
            'starts_with'   => $rule->description_starts_with,
            'contains'      => $rule->description_contains,
            'account_label' => $this->ruleAccountLabel($rule),
        ];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Find the first rule for the entry's community, by sort order, that matches.
     *
     * @param CashbookEntry $entry
     * @return AllocationRule|null
     */
    private function firstMatchingRule(CashbookEntry $entry): ?AllocationRule
    {
        return AllocationRule::where('organization_id', $entry->organization_id)
            ->where('community_id', $entry->community_id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->first(fn (AllocationRule $rule) => $this->matches($rule, $entry));
    }

    /**
     * Build the $allocation array for AllocationPostingService from a rule.
     *
     * @param AllocationRule $rule
     * @return array
     */
    private function allocationFromRule(AllocationRule $rule): array
    {
        return [
            'ledger_type' => $rule->ledger_type->value,
            'ledger_id'   => $rule->ledger_id,
            'unit_id'     => $rule->unit_id,
            'supplier_id' => $rule->supplier_id,
            'vat_type'    => $rule->vat_type,
            'remarks'     => $rule->remarks,
        ];
    }

    /**
     * The account label for a rule's target, matching AllocationPostingService.
     *
     * @param AllocationRule $rule
     * @return string|null
     */
    private function ruleAccountLabel(AllocationRule $rule): ?string
    {
        return match ($rule->ledger_type) {
            JournalLineType::GENERAL,
            JournalLineType::RESERVE_FUND => $this->posting->ledgerLabel($rule->ledger_id),
            JournalLineType::CUSTOMER     => $this->posting->customerLabel($rule->unit_id),
            JournalLineType::SUPPLIER     => $this->posting->supplierLabel($rule->supplier_id),
        };
    }

    /**
     * The next sort order for a new rule in a community.
     *
     * @param string $communityId
     * @return int
     */
    private function nextSortOrder(string $communityId): int
    {
        return (int) AllocationRule::where('community_id', $communityId)->max('sort_order') + 1;
    }
}
