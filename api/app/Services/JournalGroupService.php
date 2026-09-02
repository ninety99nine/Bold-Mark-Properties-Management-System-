<?php

namespace App\Services;

use App\Models\Community;
use App\Models\JournalGroup;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\JournalGroupResource;
use App\Http\Resources\JournalGroupResources;

class JournalGroupService extends BaseService
{
    /**
     * Return a paginated, filtered list of journal groups for a community.
     *
     * @param Community $community
     * @param array $data
     * @return JournalGroupResources|array
     */
    public function showJournalGroups(Community $community, array $data): JournalGroupResources|array
    {
        $user  = Auth::user();
        $query = JournalGroup::where('organization_id', $user->organization_id)
            ->where('community_id', $community->id);

        if (!empty($data['search'])) {
            $query->search($data['search']);
        }

        if (!request()->has('_sort')) {
            $query = $query->orderBy('name');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a journal group for a community.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function createJournalGroup(Community $community, array $data): array
    {
        $user = Auth::user();

        $group = JournalGroup::create([
            'name'            => $data['name'],
            'community_id'    => $community->id,
            'organization_id' => $user->organization_id,
        ]);

        return $this->showCreatedResource($group);
    }

    /**
     * Return a single journal group.
     *
     * @param JournalGroup $journalGroup
     * @return JournalGroupResource
     */
    public function showJournalGroup(JournalGroup $journalGroup): JournalGroupResource
    {
        return $this->showResource($journalGroup);
    }

    /**
     * Update a journal group.
     *
     * @param JournalGroup $journalGroup
     * @param array $data
     * @return array
     */
    public function updateJournalGroup(JournalGroup $journalGroup, array $data): array
    {
        $journalGroup->update(
            collect($data)->only(['name'])->filter(fn ($v) => !is_null($v))->toArray()
        );

        return $this->showUpdatedResource($journalGroup);
    }

    /**
     * Delete a journal group.
     *
     * @param JournalGroup $journalGroup
     * @return array
     */
    public function deleteJournalGroup(JournalGroup $journalGroup): array
    {
        $deleted = $journalGroup->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Journal group deleted' : 'Journal group delete unsuccessful',
        ];
    }
}
