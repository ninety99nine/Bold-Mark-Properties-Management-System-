<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy extends BasePolicy
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
     * Determine whether the user can view any organizations (organisations).
     * Only super-admins and company-admins (tenant admins) may list organizations.
     */
    public function viewAny(User $user): bool
    {
        return $this->authService->isTenantAdmin($user);
    }

    /**
     * Determine whether the user can view a specific tenant.
     * Users may only view their own tenant record.
     */
    public function view(User $user, Organization $tenant): bool
    {
        return (string) $user->organization_id === (string) $tenant->id;
    }

    /**
     * Determine whether the user can create a new tenant.
     * Only super-admins (handled by before()) may create organizations.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the tenant.
     * Organization admins may update their own tenant. Super-admins handled by before().
     */
    public function update(User $user, Organization $tenant): bool
    {
        return $this->authService->isTenantAdmin($user)
            && (string) $user->organization_id === (string) $tenant->id;
    }

    /**
     * Determine whether the user can bulk-delete organizations.
     * Only super-admins (handled by before()) may bulk-delete organizations.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the tenant.
     * Only super-admins (handled by before()) may delete organizations.
     */
    public function delete(User $user, Organization $tenant): bool
    {
        return false;
    }

    /**
     * Determine whether the user can flush all data within the tenant.
     * Company-admins may flush their own tenant. Super-admins handled by before().
     */
    public function flush(User $user, Organization $tenant): bool
    {
        return $this->authService->isTenantAdmin($user)
            && (string) $user->organization_id === (string) $tenant->id;
    }
}
