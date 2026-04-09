<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use App\Http\Resources\TenantResource;
use App\Http\Requests\Tenant\ShowTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    protected TenantService $service;

    public function __construct(TenantService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the authenticated user's tenant (company settings).
     *
     * @param Request $request
     * @return TenantResource
     */
    public function showCurrentTenant(Request $request): TenantResource
    {
        $tenant = $request->user()->tenant;

        return $this->service->showTenant($tenant);
    }

    /**
     * Update the authenticated user's tenant company settings and branding.
     *
     * @param UpdateTenantRequest $request
     * @return array
     */
    public function updateCurrentTenant(UpdateTenantRequest $request): array
    {
        $tenant = $request->user()->tenant;

        return $this->service->updateTenant($tenant, $request->validated());
    }
}
