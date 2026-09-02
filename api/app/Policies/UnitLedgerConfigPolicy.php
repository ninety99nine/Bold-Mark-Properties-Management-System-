<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\UnitLedgerConfig;
use App\Models\User;

class UnitLedgerConfigPolicy extends BasePolicy
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
     * Determine whether the user can view any ledger configs for the given unit.
     */
    public function viewAny(User $user, Unit $unit): bool
    {
        if ($unit->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }

    /**
     * Determine whether the user can view the ledger config.
     */
    public function view(User $user, Unit $unit, UnitLedgerConfig $config): bool
    {
        if ($unit->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }

    /**
     * Determine whether the user can create a ledger config for the given unit.
     */
    public function create(User $user, Unit $unit): bool
    {
        if ($unit->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }

    /**
     * Determine whether the user can update the ledger config.
     */
    public function update(User $user, Unit $unit, UnitLedgerConfig $config): bool
    {
        if ($unit->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }

    /**
     * Determine whether the user can bulk-delete ledger configs for the given unit.
     */
    public function deleteAny(User $user, Unit $unit): bool
    {
        if ($unit->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }

    /**
     * Determine whether the user can delete the ledger config.
     */
    public function delete(User $user, Unit $unit, UnitLedgerConfig $config): bool
    {
        if ($unit->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }
}
