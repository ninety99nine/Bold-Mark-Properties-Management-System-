<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\UnitChargeConfig;
use App\Models\User;

class UnitChargeConfigPolicy extends BasePolicy
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
     * Determine whether the user can view any charge configs for the given unit.
     */
    public function viewAny(User $user, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the charge config.
     */
    public function view(User $user, Unit $unit, UnitChargeConfig $config): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create a charge config for the given unit.
     */
    public function create(User $user, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the charge config.
     */
    public function update(User $user, Unit $unit, UnitChargeConfig $config): bool
    {
        return true;
    }

    /**
     * Determine whether the user can bulk-delete charge configs for the given unit.
     */
    public function deleteAny(User $user, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the charge config.
     */
    public function delete(User $user, Unit $unit, UnitChargeConfig $config): bool
    {
        return true;
    }
}
