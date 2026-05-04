<?php

namespace App\Policies;

use App\Models\ComplianceChecklist;
use App\Models\User;

class ComplianceChecklistPolicy extends BasePolicy
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
     * Determine whether the user can view any compliance checklists.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the compliance checklist.
     */
    public function view(User $user, ComplianceChecklist $checklist): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create compliance checklists.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the compliance checklist.
     */
    public function update(User $user, ComplianceChecklist $checklist): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the compliance checklist.
     */
    public function delete(User $user, ComplianceChecklist $checklist): bool
    {
        return true;
    }
}
