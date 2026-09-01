<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\Occupant;
use App\Models\User;

class OccupantPolicy extends BasePolicy
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
     * Determine whether the user can view any organizations for the given unit.
     */
    public function viewAny(User $user, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the unit occupant.
     */
    public function view(User $user, Unit $unit, Occupant $occupant): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create (move in) a occupant for the given unit.
     */
    public function create(User $user, Unit $unit): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the unit occupant.
     */
    public function update(User $user, Unit $unit, Occupant $occupant): bool
    {
        return $this->authService->hasPermission($user, 'unit.occupant.update');
    }

    /**
     * Determine whether the user can bulk-delete organizations for the given unit.
     */
    public function deleteAny(User $user, Unit $unit): bool
    {
        $unitOccupantIds = request()->input('occupant_ids', []);

        if (empty($unitOccupantIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'unit.occupant.delete');
    }

    /**
     * Determine whether the user can delete (archive) the unit occupant.
     */
    public function delete(User $user, Unit $unit, Occupant $occupant): bool
    {
        return $this->authService->hasPermission($user, 'unit.occupant.delete');
    }

    /**
     * Determine whether the user can perform a move-out for the unit occupant.
     */
    public function moveOut(User $user, Unit $unit, Occupant $occupant): bool
    {
        return true;
    }

    /**
     * Determine whether the user can reinstate an inactive occupant.
     */
    public function reinstate(User $user, Unit $unit, Occupant $occupant): bool
    {
        return true;
    }
}
