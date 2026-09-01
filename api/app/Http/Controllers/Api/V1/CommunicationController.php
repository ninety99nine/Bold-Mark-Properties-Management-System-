<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\SendCommunicationRequest;
use App\Http\Requests\Communication\ShowCommunicationsRequest;
use App\Http\Requests\Communication\UpdateCommunityEmailSettingsRequest;
use App\Http\Resources\CommunicationResources;
use App\Http\Resources\CommunityResource;
use App\Models\Communication;
use App\Models\Community;
use App\Services\CommunicationService;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    protected CommunicationService $service;

    public function __construct(CommunicationService $service)
    {
        $this->service = $service;
    }

    /**
     * List the community's sent communications (Archive tab).
     *
     * @param ShowCommunicationsRequest $request
     * @param Community $community
     * @return CommunicationResources
     */
    public function showCommunications(ShowCommunicationsRequest $request, Community $community): CommunicationResources
    {
        return $this->service->showCommunications($request->user()->organization, $community);
    }

    /**
     * Send a communication to the selected recipient groups for the community.
     *
     * @param SendCommunicationRequest $request
     * @param Community $community
     * @return array
     */
    public function sendCommunication(SendCommunicationRequest $request, Community $community): array
    {
        return $this->service->sendCommunication(
            $request->user()->organization,
            $community,
            $request->validated(),
            $request->file('attachments', [])
        );
    }

    /**
     * Re-send a previously sent community communication.
     *
     * @param Request $request
     * @param Community $community
     * @param Communication $communicationLog
     * @return array
     */
    public function resendCommunication(Request $request, Community $community, Communication $communicationLog): array
    {
        abort_unless($communicationLog->community_id === $community->id, 404);
        abort_unless($request->user()->can('sendCommunication', $community), 403);

        return $this->service->resendCommunication($communicationLog);
    }

    /**
     * List all communications across the organization (global Communicate page).
     *
     * @param Request $request
     * @return CommunicationResources
     */
    public function showAllCommunications(Request $request): CommunicationResources
    {
        return $this->service->showCommunications($request->user()->organization);
    }

    /**
     * Return the community's email branding settings (Communicate → Settings tab).
     *
     * @param Request $request
     * @param Community $community
     * @return CommunityResource
     */
    public function showEmailSettings(Request $request, Community $community): CommunityResource
    {
        abort_unless($request->user()->can('viewCommunications', $community), 403);

        return $this->service->showEmailSettings($community);
    }

    /**
     * Update the community's email logo/header/footer images.
     *
     * @param UpdateCommunityEmailSettingsRequest $request
     * @param Community $community
     * @return array
     */
    public function updateEmailSettings(UpdateCommunityEmailSettingsRequest $request, Community $community): array
    {
        return $this->service->updateEmailSettings($community, $request->validated());
    }
}
