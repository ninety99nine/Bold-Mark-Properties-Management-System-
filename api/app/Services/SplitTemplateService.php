<?php

namespace App\Services;

use App\Http\Resources\SplitTemplateResource;
use App\Http\Resources\SplitTemplateResources;
use App\Models\SplitTemplate;
use Illuminate\Support\Facades\Auth;

class SplitTemplateService extends BaseService
{
    protected string $resourceClass           = SplitTemplateResource::class;
    protected string $resourceCollectionClass = SplitTemplateResources::class;

    /**
     * Paginated, org- and community-scoped list of split templates.
     *
     * @param array $data
     * @return SplitTemplateResources|array
     */
    public function showSplitTemplates(array $data): SplitTemplateResources|array
    {
        $user = Auth::user();

        $query = SplitTemplate::where('organization_id', $user->organization_id)
            ->where('community_id', $data['community_id']);

        if (!request()->has('_sort')) {
            $query = $query->orderBy('name');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a split template for a community.
     *
     * @param array $data
     * @return array
     */
    public function createSplitTemplate(array $data): array
    {
        $user = Auth::user();

        $template = SplitTemplate::create([
            'name'            => $data['name'],
            'lines'           => $data['lines'],
            'community_id'    => $data['community_id'],
            'organization_id' => $user->organization_id,
        ]);

        return $this->showCreatedResource($template);
    }

    /**
     * Show a single split template.
     *
     * @param SplitTemplate $splitTemplate
     * @return SplitTemplateResource
     */
    public function showSplitTemplate(SplitTemplate $splitTemplate): SplitTemplateResource
    {
        return $this->showResource($splitTemplate);
    }

    /**
     * Delete a split template.
     *
     * @param SplitTemplate $splitTemplate
     * @return array
     */
    public function deleteSplitTemplate(SplitTemplate $splitTemplate): array
    {
        $deleted = $splitTemplate->delete();

        return [
            'deleted' => (bool) $deleted,
            'message' => $deleted ? 'Split template deleted' : 'Split template delete unsuccessful',
        ];
    }
}
