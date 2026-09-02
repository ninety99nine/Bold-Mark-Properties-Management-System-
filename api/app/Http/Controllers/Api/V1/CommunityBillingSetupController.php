<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\CommunityBillingSetupService;
use App\Http\Resources\CommunityBillingSetupResource;
use App\Http\Requests\Community\ShowCommunityBillingSetupRequest;
use App\Http\Requests\Community\UpdateCommunityBillingSetupRequest;
use Illuminate\Support\Facades\Auth;

class CommunityBillingSetupController extends Controller
{
    public function __construct(private readonly CommunityBillingSetupService $service)
    {
    }

    /**
     * Return the community's Default Billing Setup (charge → ledger mappings + toggles).
     *
     * @param ShowCommunityBillingSetupRequest $request
     * @param Community                        $community
     * @return CommunityBillingSetupResource
     */
    public function showBillingSetup(ShowCommunityBillingSetupRequest $request, Community $community): CommunityBillingSetupResource
    {
        $this->ensureSameOrganization($community);

        return $this->service->showBillingSetup($community);
    }

    /**
     * Update the community's Default Billing Setup.
     *
     * @param UpdateCommunityBillingSetupRequest $request
     * @param Community                          $community
     * @return array
     */
    public function updateBillingSetup(UpdateCommunityBillingSetupRequest $request, Community $community): array
    {
        $this->ensureSameOrganization($community);

        return $this->service->updateBillingSetup($community, $request->validated());
    }

    /**
     * Reject access to a community outside the authenticated user's organization.
     */
    private function ensureSameOrganization(Community $community): void
    {
        if ($community->organization_id !== Auth::user()->organization_id) {
            abort(404);
        }
    }
}
