<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Services\CommunityMemberService;
use App\Http\Requests\CommunityUser\StoreCommunityUserRequest;
use App\Http\Requests\CommunityUser\UpdateCommunityUserRequest;
use App\Http\Requests\CommunityUser\UpdateDirectorsTrusteesRequest;
use App\Http\Requests\CommunityUser\UpdatePaymentAuthorisationsRequest;
use Illuminate\Http\Request;

class CommunityUserController extends Controller
{
    protected CommunityMemberService $service;

    public function __construct(CommunityMemberService $service)
    {
        $this->service = $service;
    }

    /**
     * List a community's users (Settings → Users).
     */
    public function index(Request $request, Community $community)
    {
        abort_unless($request->user()->can('view', $community), 403);

        return $this->service->listMembers($community, $request->query('filter', 'all'));
    }

    /**
     * Add a user to the community.
     */
    public function store(StoreCommunityUserRequest $request, Community $community): array
    {
        return $this->service->createMember($community, $request->validated());
    }

    /**
     * Update a community user.
     */
    public function update(UpdateCommunityUserRequest $request, Community $community, CommunityMember $member): array
    {
        abort_unless($member->community_id === $community->id, 404);

        return $this->service->updateMember($member, $request->validated());
    }

    /**
     * Remove a community user.
     */
    public function destroy(Request $request, Community $community, CommunityMember $member): array
    {
        abort_unless($request->user()->can('update', $community), 403);
        abort_unless($member->community_id === $community->id, 404);

        return $this->service->deleteMember($member);
    }

    /**
     * Bulk-set which members are directors/trustees.
     */
    public function updateDirectorsTrustees(UpdateDirectorsTrusteesRequest $request, Community $community): array
    {
        return $this->service->updateDirectorsTrustees($community, $request->validated()['member_ids']);
    }

    /**
     * Bulk-set payment authorisers + the authorisation mode.
     */
    public function updatePaymentAuthorisations(UpdatePaymentAuthorisationsRequest $request, Community $community): array
    {
        $data = $request->validated();

        return $this->service->updatePaymentAuthorisations($community, $data['member_ids'], $data['mode']);
    }

    /**
     * Send a password-reset link to a community user.
     */
    public function resetPassword(Request $request, Community $community, CommunityMember $member): array
    {
        abort_unless($request->user()->can('update', $community), 403);
        abort_unless($member->community_id === $community->id, 404);

        return $this->service->resetPassword($member);
    }
}
