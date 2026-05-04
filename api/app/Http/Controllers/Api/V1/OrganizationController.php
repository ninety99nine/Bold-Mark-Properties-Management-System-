<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use App\Http\Resources\OrganizationResource;
use App\Http\Requests\Organization\ShowOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    protected OrganizationService $service;

    public function __construct(OrganizationService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the authenticated user's tenant (company settings).
     *
     * @param Request $request
     * @return OrganizationResource
     */
    public function showCurrentTenant(Request $request): OrganizationResource
    {
        $tenant = $request->user()->organization;

        return $this->service->showTenant($tenant);
    }

    /**
     * Update the authenticated user's tenant company settings and branding.
     *
     * @param UpdateOrganizationRequest $request
     * @return array
     */
    public function updateCurrentTenant(UpdateOrganizationRequest $request): array
    {
        $tenant = $request->user()->organization;

        return $this->service->updateTenant($tenant, $request->validated());
    }
}
