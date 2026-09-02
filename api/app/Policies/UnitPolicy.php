<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy extends BasePolicy
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
     * Determine whether the user can view any units within the given community.
     */
    public function viewAny(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the unit.
     */
    public function view(User $user, Community $community, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create units within the given community.
     */
    public function create(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the unit.
     */
    public function update(User $user, Community $community, Unit $unit): bool
    {
        return $this->authService->hasPermission($user, 'unit.update');
    }

    /**
     * Determine whether the user can bulk-delete units within the given community.
     */
    public function deleteAny(User $user, Community $community): bool
    {
        $unitIds = request()->input('unit_ids', []);

        if (empty($unitIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'unit.delete');
    }

    /**
     * Determine whether the user can delete the unit.
     */
    public function delete(User $user, Community $community, Unit $unit): bool
    {
        return $this->authService->hasPermission($user, 'unit.delete');
    }
}
