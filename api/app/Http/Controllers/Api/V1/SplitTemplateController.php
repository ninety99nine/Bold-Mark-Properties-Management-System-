<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SplitTemplate\CreateSplitTemplateRequest;
use App\Http\Requests\SplitTemplate\DeleteSplitTemplateRequest;
use App\Http\Requests\SplitTemplate\ShowSplitTemplateRequest;
use App\Http\Requests\SplitTemplate\ShowSplitTemplatesRequest;
use App\Http\Resources\SplitTemplateResource;
use App\Models\Community;
use App\Models\SplitTemplate;
use App\Services\SplitTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SplitTemplateController extends Controller
{
    protected SplitTemplateService $service;

    public function __construct(SplitTemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * Paginated list of split templates for a community.
     *
     * @param ShowSplitTemplatesRequest $request
     * @param Community                 $community
     * @return ResourceCollection
     */
    public function showSplitTemplates(ShowSplitTemplatesRequest $request, Community $community): ResourceCollection
    {
        return $this->service->showSplitTemplates(['community_id' => $community->id]);
    }

    /**
     * Create a split template for a community.
     *
     * @param CreateSplitTemplateRequest $request
     * @param Community                  $community
     * @return JsonResponse
     */
    public function createSplitTemplate(CreateSplitTemplateRequest $request, Community $community): JsonResponse
    {
        $data = array_merge($request->validated(), ['community_id' => $community->id]);

        return response()->json($this->service->createSplitTemplate($data), 201);
    }

    /**
     * Show a single split template.
     *
     * @param ShowSplitTemplateRequest $request
     * @param Community                $community
     * @param SplitTemplate            $splitTemplate
     * @return SplitTemplateResource
     */
    public function showSplitTemplate(ShowSplitTemplateRequest $request, Community $community, SplitTemplate $splitTemplate): SplitTemplateResource
    {
        return $this->service->showSplitTemplate($splitTemplate);
    }

    /**
     * Delete a split template.
     *
     * @param DeleteSplitTemplateRequest $request
     * @param Community                  $community
     * @param SplitTemplate              $splitTemplate
     * @return JsonResponse
     */
    public function deleteSplitTemplate(DeleteSplitTemplateRequest $request, Community $community, SplitTemplate $splitTemplate): JsonResponse
    {
        return response()->json($this->service->deleteSplitTemplate($splitTemplate));
    }
}
