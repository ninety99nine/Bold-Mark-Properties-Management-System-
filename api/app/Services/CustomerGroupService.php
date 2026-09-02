<?php

namespace App\Services;

use Exception;
use App\Models\Community;
use App\Models\CustomerGroup;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CustomerGroupResource;
use App\Http\Resources\CustomerGroupResources;

class CustomerGroupService extends BaseService
{
    /**
     * Return a paginated, filtered list of customer groups for a community.
     *
     * @param Community $community
     * @param array $data
     * @return CustomerGroupResources|array
     */
    public function showCustomerGroups(Community $community, array $data): CustomerGroupResources|array
    {
        $user  = Auth::user();
        $query = CustomerGroup::where('organization_id', $user->organization_id)
            ->where('community_id', $community->id)
            ->withCount('customers');

        if (!request()->has('_sort')) {
            $query = $query->orderBy('name');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a customer group for a community.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function createCustomerGroup(Community $community, array $data): array
    {
        $user = Auth::user();

        $group = CustomerGroup::create([
            'name'            => $data['name'],
            'community_id'    => $community->id,
            'organization_id' => $user->organization_id,
        ]);

        $group->loadCount('customers');

        return $this->showCreatedResource($group);
    }

    /**
     * Return a single customer group with its customer count.
     *
     * @param CustomerGroup $customerGroup
     * @return CustomerGroupResource
     */
    public function showCustomerGroup(CustomerGroup $customerGroup): CustomerGroupResource
    {
        $customerGroup->loadCount('customers');

        return $this->showResource($customerGroup);
    }

    /**
     * Update a customer group.
     *
     * @param CustomerGroup $customerGroup
     * @param array $data
     * @return array
     */
    public function updateCustomerGroup(CustomerGroup $customerGroup, array $data): array
    {
        $customerGroup->update(
            collect($data)->only(['name'])->filter(fn ($v) => !is_null($v))->toArray()
        );

        $customerGroup->loadCount('customers');

        return $this->showUpdatedResource($customerGroup);
    }

    /**
     * Delete a customer group.
     *
     * @param CustomerGroup $customerGroup
     * @return array
     */
    public function deleteCustomerGroup(CustomerGroup $customerGroup): array
    {
        $deleted = $customerGroup->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Customer group deleted' : 'Customer group delete unsuccessful',
        ];
    }
}
