<?php

namespace App\Services;

use App\Models\User;

class AuthService
{
    /**
     * Determine whether the given user has the super-admin role.
     *
     * @param User $user
     * @return bool
     */
    public function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the given user has the company-admin role.
     *
     * @param User $user
     * @return bool
     */
    public function isTenantAdmin(User $user): bool
    {
        return $user->hasRole('company-admin');
    }

    /**
     * Determine whether the given user has a named permission.
     * Super-admins and company-admins are implicitly granted all permissions.
     *
     * @param User        $user
     * @param string      $permission   e.g. 'estate.update'
     * @param mixed|null  $scopeId      Reserved for future scope-based checks
     * @return bool
     */
    public function hasPermission(User $user, string $permission, mixed $scopeId = null): bool
    {
        // Super-admins and company-admins have all permissions
        if ($user->hasRole(['super-admin', 'company-admin'])) {
            return true;
        }

        return $user->hasPermissionTo($permission);
    }

    /**
     * Determine whether the given user belongs to a specific tenant.
     *
     * @param User   $user
     * @param string $tenantId
     * @return bool
     */
    public function hasTenantAccess(User $user, string $tenantId): bool
    {
        return (string) $user->tenant_id === (string) $tenantId;
    }
}
