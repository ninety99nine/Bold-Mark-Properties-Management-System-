<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\JournalGroup;
use App\Models\User;

class JournalGroupPolicy extends BasePolicy
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
     * Determine whether the user can view any journal groups.
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
     * Determine whether the user can view the journal group.
     *
     * @param User $user
     * @param Community $community
     * @param JournalGroup $journalGroup
     * @return bool
     */
    public function view(User $user, Community $community, JournalGroup $journalGroup): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create journal groups.
     *
     * @param User $user
     * @param Community $community
     * @return bool
     */
    public function create(User $user, Community $community): bool
    {
        return $this->authService->hasPermission($user, 'journal.create');
    }

    /**
     * Determine whether the user can update the journal group.
     *
     * @param User $user
     * @param Community $community
     * @param JournalGroup $journalGroup
     * @return bool
     */
    public function update(User $user, Community $community, JournalGroup $journalGroup): bool
    {
        return $this->authService->hasPermission($user, 'journal.update');
    }

    /**
     * Determine whether the user can delete the journal group.
     *
     * @param User $user
     * @param Community $community
     * @param JournalGroup $journalGroup
     * @return bool
     */
    public function delete(User $user, Community $community, JournalGroup $journalGroup): bool
    {
        return $this->authService->hasPermission($user, 'journal.delete');
    }
}
