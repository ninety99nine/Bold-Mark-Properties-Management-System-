<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy extends BasePolicy
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
     * Determine whether the user can view any bank accounts.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the bank account.
     */
    public function view(User $user, BankAccount $bankAccount): bool
    {
        if ($bankAccount->organization_id !== $user->organization_id) {
            abort(404);
        }

        return true;
    }

    /**
     * Determine whether the user can create bank accounts.
     */
    public function create(User $user): bool
    {
        return $this->authService->hasPermission($user, 'bank_account.create');
    }

    /**
     * Determine whether the user can update the bank account.
     */
    public function update(User $user, BankAccount $bankAccount): bool
    {
        if ($bankAccount->organization_id !== $user->organization_id) {
            abort(404);
        }

        return $this->authService->hasPermission($user, 'bank_account.update');
    }

    /**
     * Determine whether the user can delete the bank account.
     */
    public function delete(User $user, BankAccount $bankAccount): bool
    {
        if ($bankAccount->organization_id !== $user->organization_id) {
            abort(404);
        }

        return $this->authService->hasPermission($user, 'bank_account.delete');
    }
}
