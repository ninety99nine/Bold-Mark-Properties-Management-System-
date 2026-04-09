<?php

namespace App\Policies;

use App\Models\Estate;
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
     * Determine whether the user can view any units within the given estate.
     */
    public function viewAny(User $user, Estate $estate): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the unit.
     */
    public function view(User $user, Estate $estate, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create units within the given estate.
     */
    public function create(User $user, Estate $estate): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the unit.
     */
    public function update(User $user, Estate $estate, Unit $unit): bool
    {
        return $this->authService->hasPermission($user, 'unit.update');
    }

    /**
     * Determine whether the user can bulk-delete units within the given estate.
     */
    public function deleteAny(User $user, Estate $estate): bool
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
    public function delete(User $user, Estate $estate, Unit $unit): bool
    {
        return $this->authService->hasPermission($user, 'unit.delete');
    }
}
