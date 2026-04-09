<?php

namespace App\Policies;

use App\Models\Owner;
use App\Models\User;

class OwnerPolicy extends BasePolicy
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
     * Determine whether the user can view any owners.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the owner.
     */
    public function view(User $user, Owner $owner): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create owners.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the owner.
     */
    public function update(User $user, Owner $owner): bool
    {
        return $this->authService->hasPermission($user, 'owner.update');
    }

    /**
     * Determine whether the user can bulk-delete owners.
     * Iterates the incoming `owner_ids` array and checks permission.
     */
    public function deleteAny(User $user): bool
    {
        $ownerIds = request()->input('owner_ids', []);

        if (empty($ownerIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'owner.delete');
    }

    /**
     * Determine whether the user can delete the owner.
     */
    public function delete(User $user, Owner $owner): bool
    {
        return $this->authService->hasPermission($user, 'owner.delete');
    }
}
