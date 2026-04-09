<?php

namespace App\Policies;

use App\Models\CashbookEntry;
use App\Models\User;

class CashbookEntryPolicy extends BasePolicy
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
     * Determine whether the user can view any cashbook entries.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the cashbook entry.
     */
    public function view(User $user, CashbookEntry $entry): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create cashbook entries.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the cashbook entry.
     */
    public function update(User $user, CashbookEntry $entry): bool
    {
        return $this->authService->hasPermission($user, 'cashbook.update');
    }

    /**
     * Determine whether the user can bulk-delete cashbook entries.
     * Iterates the incoming `entry_ids` array and checks permission.
     */
    public function deleteAny(User $user): bool
    {
        $entryIds = request()->input('entry_ids', []);

        if (empty($entryIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'cashbook.delete');
    }

    /**
     * Determine whether the user can delete the cashbook entry.
     */
    public function delete(User $user, CashbookEntry $entry): bool
    {
        return $this->authService->hasPermission($user, 'cashbook.delete');
    }

    /**
     * Determine whether the user can allocate the cashbook entry to an invoice.
     */
    public function allocate(User $user, CashbookEntry $entry): bool
    {
        return true;
    }

    /**
     * Determine whether the user can deallocate (remove) the cashbook entry from an invoice.
     */
    public function deallocate(User $user, CashbookEntry $entry): bool
    {
        return true;
    }

    /**
     * Determine whether the user can trigger auto-allocation of cashbook entries.
     */
    public function autoAllocate(User $user): bool
    {
        return true;
    }
}
