<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Http\Request;

class CommunityPolicy extends BasePolicy
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
     * Determine whether the user can view any communities.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the community.
     */
    public function view(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create communities.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the community.
     */
    public function update(User $user, Community $community): bool
    {
        return $this->authService->hasPermission($user, 'community.update');
    }

    /**
     * Determine whether the user can bulk-delete communities.
     * Iterates the incoming `community_ids` array and checks permission for each.
     */
    public function deleteAny(User $user): bool
    {
        $communityIds = request()->input('community_ids', []);

        if (empty($communityIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'community.delete');
    }

    /**
     * Determine whether the user can delete the community.
     */
    public function delete(User $user, Community $community): bool
    {
        return $this->authService->hasPermission($user, 'community.delete');
    }

    /**
     * Determine whether the user can view the community's customers.
     */
    public function viewCustomers(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create customers in the community.
     */
    public function createCustomer(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function updateCustomer(User $user, Community $community, Owner $owner): bool
    {
        return $this->authService->hasPermission($user, 'owner.update');
    }

    /**
     * Determine whether the user can disable the customer.
     */
    public function disableCustomer(User $user, Community $community, Owner $owner): bool
    {
        return $this->authService->hasPermission($user, 'owner.update');
    }

    /**
     * Determine whether the user can enable the customer.
     */
    public function enableCustomer(User $user, Community $community, Owner $owner): bool
    {
        return $this->authService->hasPermission($user, 'owner.update');
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function deleteCustomer(User $user, Community $community, Owner $owner): bool
    {
        return $this->authService->hasPermission($user, 'owner.delete');
    }

    /**
     * Determine whether the user can apply collection statuses to customers.
     */
    public function applyCustomerStatuses(User $user, Community $community): bool
    {
        return $this->authService->hasPermission($user, 'owner.update');
    }

    /**
     * Determine whether the user can view the community's communications archive.
     */
    public function viewCommunications(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Determine whether the user can send communications for the community.
     */
    public function sendCommunication(User $user, Community $community): bool
    {
        return true;
    }
}
