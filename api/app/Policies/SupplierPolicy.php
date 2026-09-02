<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy extends BasePolicy
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
     * Determine whether the user can view any suppliers.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the supplier.
     *
     * @param User $user
     * @param Supplier $supplier
     * @return bool
     */
    public function view(User $user, Supplier $supplier): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create suppliers.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $this->authService->hasPermission($user, 'supplier.create');
    }

    /**
     * Determine whether the user can update the supplier.
     *
     * @param User $user
     * @param Supplier $supplier
     * @return bool
     */
    public function update(User $user, Supplier $supplier): bool
    {
        return $this->authService->hasPermission($user, 'supplier.update');
    }

    /**
     * Determine whether the user can delete any suppliers.
     *
     * @param User $user
     * @return bool
     */
    public function deleteAny(User $user): bool
    {
        $supplierIds = request()->input('supplier_ids', []);

        if (empty($supplierIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'supplier.delete');
    }

    /**
     * Determine whether the user can delete the supplier.
     *
     * @param User $user
     * @param Supplier $supplier
     * @return bool
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->authService->hasPermission($user, 'supplier.delete');
    }
}
