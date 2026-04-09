<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy extends BasePolicy
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
     * Determine whether the user can view any tenants (organisations).
     * Only super-admins and company-admins (tenant admins) may list tenants.
     */
    public function viewAny(User $user): bool
    {
        return $this->authService->isTenantAdmin($user);
    }

    /**
     * Determine whether the user can view a specific tenant.
     * Users may only view their own tenant record.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return (string) $user->tenant_id === (string) $tenant->id;
    }

    /**
     * Determine whether the user can create a new tenant.
     * Only super-admins (handled by before()) may create tenants.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the tenant.
     * Tenant admins may update their own tenant. Super-admins handled by before().
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $this->authService->isTenantAdmin($user)
            && (string) $user->tenant_id === (string) $tenant->id;
    }

    /**
     * Determine whether the user can bulk-delete tenants.
     * Only super-admins (handled by before()) may bulk-delete tenants.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the tenant.
     * Only super-admins (handled by before()) may delete tenants.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }
}
