<?php

namespace App\Policies;

use App\Models\Estate;
use App\Models\User;
use Illuminate\Http\Request;

class EstatePolicy extends BasePolicy
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
     * Determine whether the user can view any estates.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the estate.
     */
    public function view(User $user, Estate $estate): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create estates.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the estate.
     */
    public function update(User $user, Estate $estate): bool
    {
        return $this->authService->hasPermission($user, 'estate.update');
    }

    /**
     * Determine whether the user can bulk-delete estates.
     * Iterates the incoming `estate_ids` array and checks permission for each.
     */
    public function deleteAny(User $user): bool
    {
        $estateIds = request()->input('estate_ids', []);

        if (empty($estateIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'estate.delete');
    }

    /**
     * Determine whether the user can delete the estate.
     */
    public function delete(User $user, Estate $estate): bool
    {
        return $this->authService->hasPermission($user, 'estate.delete');
    }
}
