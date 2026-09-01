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
     * Only super-admins and company-admins (organization admins) may list organizations.
     */
    public function viewAny(User $user): bool
    {
        return $this->authService->isOrganizationAdmin($user);
    }

    /**
     * Determine whether the user can view a specific organization.
     * Users may only view their own organization record.
     */
    public function view(User $user, Organization $organization): bool
    {
        return (string) $user->organization_id === (string) $organization->id;
    }

    /**
     * Determine whether the user can create a new organization.
     * Only super-admins (handled by before()) may create organizations.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the organization.
     * Organization admins may update their own organization. Super-admins handled by before().
     */
    public function update(User $user, Organization $organization): bool
    {
        return $this->authService->isOrganizationAdmin($user)
            && (string) $user->organization_id === (string) $organization->id;
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
     * Determine whether the user can delete the organization.
     * Only super-admins (handled by before()) may delete organizations.
     */
    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }

    /**
     * Determine whether the user can flush all data within the organization.
     * Company-admins may flush their own organization. Super-admins handled by before().
     */
    public function flush(User $user, Organization $organization): bool
    {
        return $this->authService->isOrganizationAdmin($user)
            && (string) $user->organization_id === (string) $organization->id;
    }
}
