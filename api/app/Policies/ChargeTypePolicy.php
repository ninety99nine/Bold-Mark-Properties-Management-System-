<?php

namespace App\Policies;

use App\Models\ChargeType;
use App\Models\User;

class ChargeTypePolicy extends BasePolicy
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
     * Determine whether the user can view any charge types.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the charge type.
     */
    public function view(User $user, ChargeType $chargeType): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create charge types.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the charge type.
     */
    public function update(User $user, ChargeType $chargeType): bool
    {
        return $this->authService->hasPermission($user, 'charge.type.update');
    }

    /**
     * Determine whether the user can bulk-delete charge types.
     * Iterates the incoming `charge_type_ids` array and checks permission.
     * System defaults (is_system = true) cannot be deleted.
     */
    public function deleteAny(User $user): bool
    {
        $chargeTypeIds = request()->input('charge_type_ids', []);

        if (empty($chargeTypeIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'charge.type.delete');
    }

    /**
     * Determine whether the user can delete the charge type.
     * System defaults (is_system = true) cannot be deleted regardless of permission.
     */
    public function delete(User $user, ChargeType $chargeType): bool
    {
        if ($chargeType->is_system) {
            return false;
        }

        return $this->authService->hasPermission($user, 'charge.type.delete');
    }
}
