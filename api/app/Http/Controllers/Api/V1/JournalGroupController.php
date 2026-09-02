<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\JournalGroup;
use App\Services\JournalGroupService;
use App\Http\Resources\JournalGroupResource;
use App\Http\Resources\JournalGroupResources;
use App\Http\Requests\JournalGroup\ShowJournalGroupsRequest;
use App\Http\Requests\JournalGroup\ShowJournalGroupRequest;
use App\Http\Requests\JournalGroup\CreateJournalGroupRequest;
use App\Http\Requests\JournalGroup\UpdateJournalGroupRequest;
use App\Http\Requests\JournalGroup\DeleteJournalGroupRequest;

class JournalGroupController extends Controller
{
    /**
     * @var JournalGroupService
     */
    protected $service;

    /**
     * JournalGroupController constructor.
     *
     * @param JournalGroupService $service
     */
    public function __construct(JournalGroupService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of journal groups for a community.
     *
     * @param ShowJournalGroupsRequest $request
     * @param Community $community
     * @return JournalGroupResources|array
     */
    public function showJournalGroups(ShowJournalGroupsRequest $request, Community $community): JournalGroupResources|array
    {
        return $this->service->showJournalGroups($community, $request->validated());
    }

    /**
     * Create a journal group for a community.
     *
     * @param CreateJournalGroupRequest $request
     * @param Community $community
     * @return array
     */
    public function createJournalGroup(CreateJournalGroupRequest $request, Community $community): array
    {
        return $this->service->createJournalGroup($community, $request->validated());
    }

    /**
     * Return a single journal group.
     *
     * @param ShowJournalGroupRequest $request
     * @param Community $community
     * @param JournalGroup $journalGroup
     * @return JournalGroupResource
     */
    public function showJournalGroup(ShowJournalGroupRequest $request, Community $community, JournalGroup $journalGroup): JournalGroupResource
    {
        return $this->service->showJournalGroup($journalGroup);
    }

    /**
     * Update a journal group.
     *
     * @param UpdateJournalGroupRequest $request
     * @param Community $community
     * @param JournalGroup $journalGroup
     * @return array
     */
    public function updateJournalGroup(UpdateJournalGroupRequest $request, Community $community, JournalGroup $journalGroup): array
    {
        return $this->service->updateJournalGroup($journalGroup, $request->validated());
    }

    /**
     * Delete a journal group.
     *
     * @param DeleteJournalGroupRequest $request
     * @param Community $community
     * @param JournalGroup $journalGroup
     * @return array
     */
    public function deleteJournalGroup(DeleteJournalGroupRequest $request, Community $community, JournalGroup $journalGroup): array
    {
        return $this->service->deleteJournalGroup($journalGroup);
    }
}
