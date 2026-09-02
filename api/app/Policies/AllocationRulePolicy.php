<?php

namespace App\Policies;

use App\Models\AllocationRule;
use App\Models\User;

class AllocationRulePolicy extends BasePolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($this->authService->isSuperAdmin($user)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AllocationRule $allocationRule): bool
    {
        if ($allocationRule->organization_id !== $user->organization_id) {
            abort(404);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AllocationRule $allocationRule): bool
    {
        if ($allocationRule->organization_id !== $user->organization_id) {
            abort(404);
        }

        return true;
    }

    public function delete(User $user, AllocationRule $allocationRule): bool
    {
        if ($allocationRule->organization_id !== $user->organization_id) {
            abort(404);
        }

        return true;
    }
}
