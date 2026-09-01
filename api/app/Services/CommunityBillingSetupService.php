<?php

namespace App\Services;

use App\Models\Community;
use App\Models\CommunityBillingSetup;
use App\Http\Resources\CommunityBillingSetupResource;

class CommunityBillingSetupService extends BaseService
{
    /**
     * Return the community's billing setup, creating an empty default row on first
     * access so the settings form always has something to bind to.
     *
     * @param Community $community
     * @return CommunityBillingSetupResource
     */
    public function showBillingSetup(Community $community): CommunityBillingSetupResource
    {
        // fresh() clears wasRecentlyCreated so a first-access GET returns 200, not 201.
        return new CommunityBillingSetupResource($this->resolveSetup($community)->fresh());
    }

    /**
     * Update (or create) the community's billing setup.
     *
     * @param Community $community
     * @param array     $data
     * @return array
     */
    public function updateBillingSetup(Community $community, array $data): array
    {
        $setup = $this->resolveSetup($community);

        $fields = array_merge(CommunityBillingSetup::LEDGER_FIELDS, CommunityBillingSetup::BOOLEAN_FIELDS);

        $setup->update(collect($data)->only($fields)->toArray());

        return [
            'data'    => new CommunityBillingSetupResource($setup->fresh()),
            'message' => 'Billing setup updated successfully',
        ];
    }

    /**
     * Fetch the community's setup row or create an empty one scoped to its org.
     *
     * @param Community $community
     * @return CommunityBillingSetup
     */
    private function resolveSetup(Community $community): CommunityBillingSetup
    {
        return CommunityBillingSetup::firstOrCreate(
            ['community_id' => $community->id],
            ['organization_id' => $community->organization_id],
        );
    }
}
