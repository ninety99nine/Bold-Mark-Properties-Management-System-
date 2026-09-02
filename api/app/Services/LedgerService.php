<?php

namespace App\Services;

use Exception;
use App\Models\Ledger;
use App\Enums\VatType;
use App\Enums\FinancialCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\LedgerResource;
use App\Http\Resources\LedgerResources;

class LedgerService extends BaseService
{
    protected array $allowedRelationships = ['communities', 'subAccounts', 'parent'];

    /**
     * Return a paginated, filtered list of ledgers for the authenticated occupant.
     *
     * @param array $data
     * @return LedgerResources
     */
    public function showLedgers(array $data): LedgerResources
    {
        $user  = Auth::user();
        $query = Ledger::where('organization_id', $user->organization_id);

        if (!empty($data['applies_to'])) {
            $query->where('applies_to', $data['applies_to']);
        }

        if (!empty($data['fund'])) {
            $query->where('fund', $data['fund']);
        }

        if (isset($data['is_recurring'])) {
            $isRecurring = $data['is_recurring'] === 'true' || $data['is_recurring'] === true || $data['is_recurring'] === 1;
            $query->where('is_recurring', $isRecurring);
        }

        if (isset($data['is_active'])) {
            $isActive = $data['is_active'] === 'true' || $data['is_active'] === true || $data['is_active'] === 1;
            $query->where('is_active', $isActive);
        }

        if (isset($data['is_system'])) {
            $isSystem = $data['is_system'] === 'true' || $data['is_system'] === true || $data['is_system'] === 1;
            $query->where('is_system', $isSystem);
        }

        // Default sort by sort_order then name; override with _sort if provided
        if (!request()->has('_sort')) {
            $query = $query->orderBy('sort_order')->orderBy('name');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Return the chart of accounts as WeConnectU-style category groups: each MAIN
     * account (X000/000 header) with its sub-accounts nested underneath. Supports a
     * `?fund=main|reserve` filter so the Reserve Fund Ledger tab reuses this method.
     *
     * @param array $data
     * @return array{data: array}
     */
    public function showGroupedLedgers(array $data): array
    {
        $user  = Auth::user();
        $query = Ledger::query()
            ->where('organization_id', $user->organization_id)
            ->with(['subAccounts' => fn ($q) => $q->orderBy('code')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('code');

        if (!empty($data['fund'])) {
            $query->where('fund', $data['fund']);
        }

        $groups = $query->get()->map(fn (Ledger $main): array => [
            'id'                 => $main->id,
            'code'               => $main->code,
            'name'               => $main->name,
            'category'           => $main->category,
            'account_type'       => $main->account_type,
            'financial_category' => $main->financial_category?->value,
            'fund'               => $main->fund,
            'allow_sub_accounts' => (bool) $main->allow_sub_accounts,
            'main'               => new LedgerResource($main),
            'sub_accounts'       => LedgerResource::collection($main->subAccounts),
        ])->all();

        return ['data' => $groups];
    }

    /**
     * Options used by the "Add General Ledger Account" form: the parent picker,
     * financial categories, account types and tax types.
     *
     * @return array
     */
    public function ledgerOptions(): array
    {
        $user = Auth::user();

        $mainAccounts = Ledger::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('parent_id')
            ->where('allow_sub_accounts', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Ledger $ledger): array => [
                'id'    => $ledger->id,
                'code'  => $ledger->code,
                'label' => $ledger->code . ' - ' . $ledger->name,
            ])
            ->all();

        return [
            'main_accounts'        => $mainAccounts,
            'financial_categories' => FinancialCategory::options(),
            'account_types'        => [
                ['value' => 'income_statement', 'label' => 'Income Statement'],
                ['value' => 'balance_sheet',    'label' => 'Balance Sheet'],
            ],
            'tax_types'            => VatType::options(),
        ];
    }

    /**
     * Create a new General Ledger account (main or sub) for the authenticated occupant.
     *
     * Main Account: user supplies a 4-digit prefix; code becomes "{prefix}/000".
     * Sub-Account : user picks a parent main account; code auto = nextSubAccountCode.
     * Singleton categories (AP / AR / Retained Income) are rejected when one already exists.
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    /**
     * Preview the auto-generated account number for a new GL account, without
     * creating it — powers the read-only "Account Number" field in the modal.
     * Main account → "{prefix}/000"; sub-account → next free code under the parent.
     *
     * @param array $data  type (main|sub), prefix (main), parent_id (sub)
     * @return array{code: string}
     */
    public function nextCodePreview(array $data): array
    {
        $orgId = Auth::user()->organization_id;
        $type  = $data['type'] ?? 'sub';

        if ($type === 'main') {
            $prefix = trim((string) ($data['prefix'] ?? ''));

            return ['code' => $prefix !== '' ? $prefix . '/000' : ''];
        }

        $parent = Ledger::where('organization_id', $orgId)->find($data['parent_id'] ?? null);

        return ['code' => $parent ? Ledger::nextSubAccountCode($orgId, $parent->code) : ''];
    }

    public function createLedger(array $data): array
    {
        $user  = Auth::user();
        $orgId = $user->organization_id;

        // Legacy Ledger create (no GL `type` field, uses applies_to/is_recurring).
        if (empty($data['type'])) {
            $ledger = Ledger::create(array_merge(
                collect($data)->only(['name', 'description', 'applies_to', 'is_recurring', 'is_active', 'sort_order'])->toArray(),
                [
                    'organization_id' => $orgId,
                    'is_system'       => false,
                    'is_active'       => $data['is_active'] ?? true,
                ]
            ));

            return $this->showCreatedResource($ledger);
        }

        $type = $data['type'];

        $category = isset($data['financial_category'])
            ? FinancialCategory::from($data['financial_category'])
            : null;

        // Enforce singleton categories (Accounts Payable / Receivable / Retained Income).
        if ($category && $category->isSingleton()) {
            $exists = Ledger::where('organization_id', $orgId)
                ->where('financial_category', $category)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'financial_category' => "Only 1 GL account is permitted for the {$category->label()} category.",
                ]);
            }
        }

        if ($type === 'main') {
            $prefix = $data['prefix'];
            $fund   = $data['fund'] ?? 'main';

            $ledger = Ledger::create(array_filter([
                'organization_id'    => $orgId,
                'code'               => $prefix . '/000',
                'name'               => $data['name'],
                'description'        => $data['description'] ?? null,
                'account_type'       => $data['account_type'] ?? null,
                'financial_category' => $category,
                'tax_type'           => $data['tax_type'] ?? null,
                'fund'               => $fund,
                'parent_id'          => null,
                'allow_sub_accounts' => $data['allow_sub_accounts'] ?? true,
                'is_system'          => false,
                'is_active'          => true,
                'is_recurring'       => false,
                'applies_to'         => 'owner',
                'sort_order'         => $data['sort_order'] ?? null,
            ], fn ($v) => !is_null($v)));

            return $this->showCreatedResource($ledger);
        }

        // Sub-account: inherit from parent, auto-generate code.
        /** @var Ledger $parent */
        $parent = Ledger::where('organization_id', $orgId)
            ->findOrFail($data['parent_id']);

        $code = Ledger::nextSubAccountCode($orgId, $parent->code);

        $ledger = Ledger::create(array_filter([
            'organization_id'    => $orgId,
            'code'               => $code,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'account_type'       => $data['account_type'] ?? $parent->account_type,
            'financial_category' => $category ?? $parent->financial_category,
            'tax_type'           => $data['tax_type'] ?? $parent->tax_type,
            'fund'               => $parent->fund,
            'parent_id'          => $parent->id,
            'allow_sub_accounts' => false,
            'is_system'          => false,
            'is_active'          => true,
            'is_recurring'       => false,
            'applies_to'         => $parent->applies_to instanceof \BackedEnum ? $parent->applies_to->value : ($parent->applies_to ?? 'owner'),
            'sort_order'         => $data['sort_order'] ?? null,
        ], fn ($v) => !is_null($v)));

        return $this->showCreatedResource($ledger);
    }

    /**
     * Bulk delete ledgers by an array of IDs (skips system types).
     *
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteLedgers(array $ids): array
    {
        $user        = Auth::user();
        $ledgers = Ledger::whereIn('id', $ids)
            ->where('organization_id', $user->organization_id)
            ->where('is_system', false)  // Never delete system types in bulk
            ->get()
            ->reject(fn (Ledger $ledger): bool => $this->isProtected($ledger));

        $total = $ledgers->count();

        if ($total === 0) {
            throw new Exception('No Ledgers deleted (system / control accounts cannot be deleted)');
        }

        foreach ($ledgers as $ledger) {
            $ledger->delete();
        }

        $label = $total === 1 ? 'Ledger' : 'Ledgers';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Return a single ledger resource.
     *
     * @param Ledger $ledger
     * @return LedgerResource
     */
    public function showLedger(Ledger $ledger): LedgerResource
    {
        return $this->showResource($ledger);
    }

    /**
     * Update a ledger.
     * System / control accounts may only have name, description, and sort_order changed.
     *
     * @param Ledger $ledger
     * @param array      $data
     * @return array
     */
    public function updateLedger(Ledger $ledger, array $data): array
    {
        if ($this->isProtected($ledger)) {
            // System / control accounts: only allow safe cosmetic fields (plus the
            // Budget Item flag, which is togglable on every account like WeConnectU).
            $updateData = collect($data)
                ->only(['name', 'description', 'sort_order', 'is_budget_item'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();
        } else {
            $updateData = collect($data)
                ->only(['name', 'description', 'account_type', 'financial_category', 'tax_type', 'allow_sub_accounts', 'is_budget_item', 'is_active', 'sort_order'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();
        }

        $ledger->update($updateData);

        return $this->showUpdatedResource($ledger);
    }

    /**
     * Delete a single ledger (system / control accounts cannot be deleted).
     *
     * @param Ledger $ledger
     * @return array
     * @throws Exception
     */
    public function deleteLedger(Ledger $ledger): array
    {
        if ($this->isProtected($ledger)) {
            throw new Exception('System / control accounts cannot be deleted');
        }

        $deleted = $ledger->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Ledger deleted' : 'Ledger delete unsuccessful',
        ];
    }

    /**
     * Whether a ledger is protected from edits/deletion (system flag or a
     * singleton control-account category — AP / AR / Retained Income).
     *
     * @param Ledger $ledger
     * @return bool
     */
    protected function isProtected(Ledger $ledger): bool
    {
        return $ledger->is_system
            || ($ledger->financial_category instanceof FinancialCategory && $ledger->financial_category->isSingleton());
    }
}
