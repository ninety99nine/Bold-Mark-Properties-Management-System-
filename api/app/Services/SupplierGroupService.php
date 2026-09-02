<?php

namespace App\Services;

use Exception;
use App\Models\SupplierGroup;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\SupplierGroupResource;
use App\Http\Resources\SupplierGroupResources;

class SupplierGroupService extends BaseService
{
    /**
     * Return a paginated, filtered list of supplier groups for the authenticated
     * user's organization. Optionally scoped to a single community.
     *
     * @param array $data
     * @return SupplierGroupResources|array
     */
    public function showSupplierGroups(array $data): SupplierGroupResources|array
    {
        $user  = Auth::user();
        $query = SupplierGroup::where('organization_id', $user->organization_id)
            ->withCount('suppliers');

        if (!empty($data['community_id'])) {
            $query->forCommunity($data['community_id']);
        }

        if (!empty($data['search'])) {
            $query->search($data['search']);
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a supplier group for the authenticated user's organization.
     *
     * @param array $data
     * @return array
     */
    public function createSupplierGroup(array $data): array
    {
        $user = Auth::user();

        $data['organization_id'] = $user->organization_id;

        $group = SupplierGroup::create($data);

        return $this->showCreatedResource($group);
    }

    /**
     * Return a single supplier group resource.
     *
     * @param SupplierGroup $supplierGroup
     * @return SupplierGroupResource
     */
    public function showSupplierGroup(SupplierGroup $supplierGroup): SupplierGroupResource
    {
        return $this->showResource($supplierGroup);
    }

    /**
     * Delete a single supplier group.
     *
     * @param SupplierGroup $supplierGroup
     * @return array
     */
    public function deleteSupplierGroup(SupplierGroup $supplierGroup): array
    {
        $deleted = $supplierGroup->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Supplier group deleted' : 'Supplier group delete unsuccessful',
        ];
    }
}
