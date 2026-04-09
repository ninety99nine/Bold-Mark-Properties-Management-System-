<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
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
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the target user.
     */
    public function view(User $user, User $target): bool
    {
        return true;
    }

    /**
     * Determine whether the user can invite (create) new users.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the target user.
     * Company admins can update anyone. Users can always update themselves.
     */
    public function update(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }

        return $user->hasRole(['super-admin', 'company-admin']);
    }

    /**
     * Determine whether the user can bulk-delete users.
     */
    public function deleteAny(User $user): bool
    {
        $userIds = request()->input('user_ids', []);

        if (empty($userIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'user.delete');
    }

    /**
     * Determine whether the user can delete the target user.
     * A user cannot delete themselves.
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        return $this->authService->hasPermission($user, 'user.delete');
    }
}
