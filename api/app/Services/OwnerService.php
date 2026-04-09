<?php

namespace App\Services;

use Exception;
use App\Models\Owner;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\OwnerResources;

class OwnerService extends BaseService
{
    /**
     * Return a paginated, filtered list of owners for the authenticated tenant.
     *
     * @param array $data
     * @return OwnerResources
     */
    public function showOwners(array $data): OwnerResources
    {
        $user  = Auth::user();
        $query = Owner::where('tenant_id', $user->tenant_id)
            ->with(['unit.estate']);

        if (!empty($data['estate_id'])) {
            $query->whereHas('unit', fn($q) => $q->where('estate_id', $data['estate_id']));
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Bulk delete owners by an array of IDs.
     *
     * @param array $ownerIds
     * @return array
     * @throws Exception
     */
    public function deleteOwners(array $ownerIds): array
    {
        $user   = Auth::user();
        $owners = Owner::whereIn('id', $ownerIds)
            ->where('tenant_id', $user->tenant_id)
            ->get();

        $total = $owners->count();

        if ($total === 0) {
            throw new Exception('No Owners deleted');
        }

        foreach ($owners as $owner) {
            $owner->delete();
        }

        $label = $total === 1 ? 'Owner' : 'Owners';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Return a single owner resource with its relationships loaded.
     *
     * @param Owner $owner
     * @return OwnerResource
     */
    public function showOwner(Owner $owner): OwnerResource
    {
        $owner->load(['unit.estate', 'invoices.chargeType']);

        return $this->showResource($owner);
    }

    /**
     * Update an owner's details.
     *
     * @param Owner $owner
     * @param array $data
     * @return array
     */
    public function updateOwner(Owner $owner, array $data): array
    {
        $owner->update(
            collect($data)
                ->only(['full_name', 'email', 'phone', 'id_number', 'address'])
                ->filter(fn($v) => !is_null($v))
                ->toArray()
        );

        return $this->showUpdatedResource($owner);
    }

    /**
     * Delete a single owner.
     *
     * @param Owner $owner
     * @return array
     */
    public function deleteOwner(Owner $owner): array
    {
        $deleted = $owner->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Owner deleted' : 'Owner delete unsuccessful',
        ];
    }
}
