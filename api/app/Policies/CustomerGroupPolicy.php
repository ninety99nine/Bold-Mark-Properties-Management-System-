<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\CustomerGroup;
use App\Models\User;

class CustomerGroupPolicy extends BasePolicy
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
     * Determine whether the user can view any customer groups.
     *
     * @param User $user
     * @param Community $community
     * @return bool
     */
    public function viewAny(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the customer group.
     *
     * @param User $user
     * @param Community $community
     * @param CustomerGroup $customerGroup
     * @return bool
     */
    public function view(User $user, Community $community, CustomerGroup $customerGroup): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create customer groups.
     *
     * @param User $user
     * @param Community $community
     * @return bool
     */
    public function create(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the customer group.
     *
     * @param User $user
     * @param Community $community
     * @param CustomerGroup $customerGroup
     * @return bool
     */
    public function update(User $user, Community $community, CustomerGroup $customerGroup): bool
    {
        return $this->authService->hasPermission($user, 'owner.update');
    }

    /**
     * Determine whether the user can delete the customer group.
     *
     * @param User $user
     * @param Community $community
     * @param CustomerGroup $customerGroup
     * @return bool
     */
    public function delete(User $user, Community $community, CustomerGroup $customerGroup): bool
    {
        return $this->authService->hasPermission($user, 'owner.delete');
    }
}
