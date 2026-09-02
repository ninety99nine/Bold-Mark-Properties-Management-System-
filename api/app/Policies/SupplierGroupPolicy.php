<?php

namespace App\Policies;

use App\Models\SupplierGroup;
use App\Models\User;

class SupplierGroupPolicy extends BasePolicy
{
    /**
     * Grant all permissions to super admins.
     *
     * @param User $user
     * @param string $ability
     * @return bool|null
     */
    public function before(User $user, string $ability): bool|null
    {
        return $this->authService->isSuperAdmin($user) ? true : null;
    }

    /**
     * Determine whether the user can view any supplier groups.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the supplier group.
     *
     * @param User $user
     * @param SupplierGroup $supplierGroup
     * @return bool
     */
    public function view(User $user, SupplierGroup $supplierGroup): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create supplier groups.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $this->authService->hasPermission($user, 'supplier.create');
    }

    /**
     * Determine whether the user can delete the supplier group.
     *
     * @param User $user
     * @param SupplierGroup $supplierGroup
     * @return bool
     */
    public function delete(User $user, SupplierGroup $supplierGroup): bool
    {
        return $this->authService->hasPermission($user, 'supplier.delete');
    }
}
