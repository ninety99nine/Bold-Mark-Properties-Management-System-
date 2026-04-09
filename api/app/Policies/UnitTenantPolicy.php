<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\UnitTenant;
use App\Models\User;

class UnitTenantPolicy extends BasePolicy
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
     * Determine whether the user can view any tenants for the given unit.
     */
    public function viewAny(User $user, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the unit tenant.
     */
    public function view(User $user, Unit $unit, UnitTenant $unitTenant): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create (move in) a tenant for the given unit.
     */
    public function create(User $user, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the unit tenant.
     */
    public function update(User $user, Unit $unit, UnitTenant $unitTenant): bool
    {
        return $this->authService->hasPermission($user, 'unit.tenant.update');
    }

    /**
     * Determine whether the user can bulk-delete tenants for the given unit.
     */
    public function deleteAny(User $user, Unit $unit): bool
    {
        $unitTenantIds = request()->input('unit_tenant_ids', []);

        if (empty($unitTenantIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'unit.tenant.delete');
    }

    /**
     * Determine whether the user can delete (archive) the unit tenant.
     */
    public function delete(User $user, Unit $unit, UnitTenant $unitTenant): bool
    {
        return $this->authService->hasPermission($user, 'unit.tenant.delete');
    }

    /**
     * Determine whether the user can perform a move-out for the unit tenant.
     */
    public function moveOut(User $user, Unit $unit, UnitTenant $unitTenant): bool
    {
        return true;
    }

    /**
     * Determine whether the user can reinstate an inactive tenant.
     */
    public function reinstate(User $user, Unit $unit, UnitTenant $unitTenant): bool
    {
        return true;
    }
}
