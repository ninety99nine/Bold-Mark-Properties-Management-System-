<?php

namespace App\Policies;

use App\Models\ComplianceTemplate;
use App\Models\User;

class ComplianceTemplatePolicy extends BasePolicy
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
     * Determine whether the user can view any compliance templates.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the compliance template.
     */
    public function view(User $user, ComplianceTemplate $template): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create compliance templates.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the compliance template.
     */
    public function update(User $user, ComplianceTemplate $template): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the compliance template.
     */
    public function delete(User $user, ComplianceTemplate $template): bool
    {
        return true;
    }
}
