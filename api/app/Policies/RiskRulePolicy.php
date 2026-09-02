<?php

namespace App\Policies;

use App\Models\RiskRule;
use App\Models\User;

class RiskRulePolicy extends BasePolicy
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
     * Determine whether the user can view any risk rules.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the risk rule.
     */
    public function view(User $user, RiskRule $riskRule): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create risk rules.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the risk rule.
     */
    public function update(User $user, RiskRule $riskRule): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the risk rule.
     */
    public function delete(User $user, RiskRule $riskRule): bool
    {
        return true;
    }
}
