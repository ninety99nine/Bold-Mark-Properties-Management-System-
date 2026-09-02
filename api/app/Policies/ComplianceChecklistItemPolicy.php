<?php

namespace App\Policies;

use App\Models\ComplianceChecklistItem;
use App\Models\User;

class ComplianceChecklistItemPolicy extends BasePolicy
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
     * Determine whether the user can view any compliance checklist items.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create compliance checklist items.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the compliance checklist item.
     */
    public function update(User $user, ComplianceChecklistItem $item): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the compliance checklist item.
     */
    public function delete(User $user, ComplianceChecklistItem $item): bool
    {
        return true;
    }
}
