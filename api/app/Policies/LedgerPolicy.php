<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\User;
use App\Enums\FinancialCategory;

class LedgerPolicy extends BasePolicy
{
    /**
     * Super-admins bypass all policy checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($this->authService->isSuperAdmin($user)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any ledgers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the ledger.
     */
    public function view(User $user, Ledger $ledger): bool
    {
        if ($ledger->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }

    /**
     * Determine whether the user can create ledgers.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the ledger.
     */
    public function update(User $user, Ledger $ledger): bool
    {
        if ($ledger->organization_id !== $user->organization_id) {
            abort(404);
        }
        return $this->authService->hasPermission($user, 'charge.type.update');
    }

    /**
     * Determine whether the user can bulk-delete ledgers.
     */
    public function deleteAny(User $user): bool
    {
        return $this->authService->hasPermission($user, 'charge.type.delete');
    }

    /**
     * Determine whether the user can delete the ledger.
     * System defaults (is_system = true) cannot be deleted regardless of permission.
     */
    public function delete(User $user, Ledger $ledger): bool
    {
        if ($ledger->organization_id !== $user->organization_id) {
            abort(404);
        }
        // System defaults and singleton control accounts (AP / AR / Retained
        // Income) are protected — they can never be deleted.
        if ($ledger->is_system) {
            return false;
        }
        if ($ledger->financial_category instanceof FinancialCategory && $ledger->financial_category->isSingleton()) {
            return false;
        }
        return $this->authService->hasPermission($user, 'charge.type.delete');
    }
}
