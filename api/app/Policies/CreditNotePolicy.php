<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy extends BasePolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($this->authService->isSuperAdmin($user)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->organization_id !== $user->organization_id) {
            abort(404);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function deleteAny(User $user): bool
    {
        return $this->authService->hasPermission($user, 'invoice.delete');
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->organization_id !== $user->organization_id) {
            abort(404);
        }

        return $this->authService->hasPermission($user, 'invoice.delete');
    }
}
