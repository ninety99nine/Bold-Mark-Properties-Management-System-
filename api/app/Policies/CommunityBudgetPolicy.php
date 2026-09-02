<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;

class CommunityBudgetPolicy extends BasePolicy
{
    /**
     * Grant all permissions to super admins.
     *
     * @param User $user
     * @param string $ability
     * @return bool|null
     */
    public function before(User $user, string $ability): bool|null
    {
        return $this->authService->isSuperAdmin($user) ? true : null;
    }

    /**
     * Determine whether the user can view budgets for a community.
     *
     * @param User $user
     * @param Community $community
     * @return bool
     */
    public function viewAny(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can manage (upsert / lock / import) budgets.
     *
     * @param User $user
     * @param Community $community
     * @return bool
     */
    public function manage(User $user, Community $community): bool
    {
        return $this->authService->hasPermission($user, 'ledger.update');
    }
}
