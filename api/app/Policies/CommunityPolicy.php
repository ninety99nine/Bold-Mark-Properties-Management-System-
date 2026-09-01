<?php

namespace App\Policies;

use App\Models\Community;
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
}
