<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\BankAccount;
use App\Enums\FinancialCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\BankAccountResource;
use App\Http\Resources\BankAccountResources;

class BankAccountService extends BaseService
{
    /**
     * Return a paginated, filtered list of bank accounts for the authenticated
     * user's organization. Optionally scoped to a single community.
     *
     * Filters:
     *   community_id → only accounts for that community
     *   is_active    → true/false (defaults to active only when omitted)
     *
     * @param array $data
     * @return BankAccountResources
     */
    public function showBankAccounts(array $data): BankAccountResources
    {
        $user  = Auth::user();
        $query = BankAccount::where('organization_id', $user->organization_id);

        if (!empty($data['community_id'])) {
            $query->where('community_id', $data['community_id']);
        }

        if (array_key_exists('is_active', $data)) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        } else {
            $query->where('is_active', true);
        }

        if (!request()->has('_sort')) {
            $query->orderBy('type')->orderBy('name');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a bank account for the authenticated user's organization.
     *
     * @param array $data
     * @return array
     */
    public function createBankAccount(array $data): array
    {
        $user = Auth::user();

        $data['organization_id'] = $user->organization_id;

        $bankAccount = DB::transaction(function () use ($data, $user) {
            $communityId = $data['community_id'] ?? null;

            // The first cashbook for a community becomes its default automatically.
            $isFirst = $communityId !== null
                && !BankAccount::where('organization_id', $user->organization_id)
                    ->where('community_id', $communityId)
                    ->exists();

            $data['is_default'] = !empty($data['is_default']) || $isFirst;

            $bankAccount = BankAccount::create($data);

            if ($bankAccount->is_default) {
                $this->unsetOtherDefaults($bankAccount);
            }

            $this->ensureLedger($bankAccount);

            return $bankAccount;
        });

        return $this->showCreatedResource($bankAccount);
    }

    /**
     * Return a single bank account resource.
     *
     * @param BankAccount $bankAccount
     * @return BankAccountResource
     */
    public function showBankAccount(BankAccount $bankAccount): BankAccountResource
    {
        return $this->showResource($bankAccount);
    }

    /**
     * Update a bank account's details.
     *
     * @param BankAccount $bankAccount
     * @param array $data
     * @return array
     */
    public function updateBankAccount(BankAccount $bankAccount, array $data): array
    {
        $bankAccount = DB::transaction(function () use ($bankAccount, $data) {
            $bankAccount->update($data);

            // Only one default cashbook per community.
            if (array_key_exists('is_default', $data) && $bankAccount->is_default) {
                $this->unsetOtherDefaults($bankAccount);
            }

            // Keep the GL ledger present, and its name in sync with bank/account changes.
            $ledger = $this->ensureLedger($bankAccount);

            if ($ledger && array_intersect(['bank_name', 'account_number', 'type'], array_keys($data))) {
                $ledger->update(['name' => $this->ledgerName($bankAccount)]);
            }

            return $bankAccount;
        });

        return $this->showUpdatedResource($bankAccount);
    }

    /**
     * Delete a single bank account.
     *
     * @param BankAccount $bankAccount
     * @return array
     */
    public function deleteBankAccount(BankAccount $bankAccount): array
    {
        $deleted = $bankAccount->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Bank account deleted' : 'Bank account delete unsuccessful',
        ];
    }

    /**
     * Ensure the bank account has its 8000/00n Bank GL ledger, creating and
     * assigning one if missing. Returns the resolved ledger.
     *
     * @param BankAccount $bankAccount
     * @return Ledger|null
     */
    public function ensureLedger(BankAccount $bankAccount): ?Ledger
    {
        if ($bankAccount->ledger_id) {
            return $bankAccount->ledger;
        }

        $orgId = $bankAccount->organization_id;
        $code  = Ledger::nextCode($orgId, '8000');

        $ledger = Ledger::create([
            'code'               => $code,
            'category'           => '8000/000 - BANK',
            'name'               => $this->ledgerName($bankAccount),
            'account_type'       => 'balance_sheet',
            'financial_category' => FinancialCategory::BANK,
            'fund'               => 'main',
            'is_system'          => true,
            'is_active'          => true,
            'organization_id'    => $orgId,
        ]);

        $bankAccount->forceFill(['ledger_id' => $ledger->id])->save();

        return $ledger->fresh();
    }

    /**
     * Build the GL ledger display name for a bank account, matching WeConnectU's
     * "Standard Bank Current 401794555" format.
     *
     * @param BankAccount $bankAccount
     * @return string
     */
    protected function ledgerName(BankAccount $bankAccount): string
    {
        $type = $bankAccount->type instanceof \BackedEnum ? $bankAccount->type->value : $bankAccount->type;

        $name = trim(implode(' ', array_filter([
            $bankAccount->bank_name,
            $type,
            $bankAccount->account_number,
        ])));

        return $name !== '' ? $name : $bankAccount->name;
    }

    /**
     * Clear the default flag on all other bank accounts in the same community.
     *
     * @param BankAccount $bankAccount
     * @return void
     */
    protected function unsetOtherDefaults(BankAccount $bankAccount): void
    {
        BankAccount::where('organization_id', $bankAccount->organization_id)
            ->where('community_id', $bankAccount->community_id)
            ->whereKeyNot($bankAccount->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
