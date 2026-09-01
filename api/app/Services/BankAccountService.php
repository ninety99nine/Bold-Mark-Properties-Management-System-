<?php

namespace App\Services;

use App\Models\BankAccount;
use Illuminate\Support\Facades\Auth;
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
}
