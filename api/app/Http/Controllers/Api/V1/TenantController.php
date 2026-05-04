<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Estate;
use App\Models\Unit;
use App\Models\Tenant;
use App\Services\TenantService;
use App\Http\Resources\TenantResource;
use App\Http\Resources\TenantResources;
use App\Http\Requests\Tenant\ShowTenantsRequest;
use App\Http\Requests\Tenant\CreateTenantRequest;
use App\Http\Requests\Tenant\ShowTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Requests\Tenant\MoveOutTenantRequest;
use App\Http\Requests\Tenant\DeleteTenantRequest;
use App\Http\Requests\Tenant\DeleteTenantsRequest;
use App\Http\Requests\Tenant\UploadLeaseDocumentRequest;
use App\Http\Requests\Tenant\DeleteLeaseDocumentRequest;
use App\Http\Requests\Tenant\ReinstateTenantRequest;

class TenantController extends Controller
{
    protected TenantService $service;

    public function __construct(TenantService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of tenant history for a unit.
     */
    public function showTenants(ShowTenantsRequest $request, Estate $estate, Unit $unit): TenantResources
    {
        return $this->service->showTenants($unit, $request->validated());
    }

    /**
     * Move in a new tenant (creates a unit tenant record and updates unit occupancy).
     */
    public function createTenant(CreateTenantRequest $request, Estate $estate, Unit $unit): array
    {
        return $this->service->createTenant($unit, $request->validated());
    }

    /**
     * Bulk delete unit tenant records.
     */
    public function deleteTenants(DeleteTenantsRequest $request, Estate $estate, Unit $unit): array
    {
        return $this->service->deleteTenants($unit, $request->input('tenant_ids', []));
    }

    /**
     * Return a single unit tenant record.
     */
    public function showTenant(ShowTenantRequest $request, Estate $estate, Unit $unit, Tenant $tenant): TenantResource
    {
        return $this->service->showTenant($unit, $tenant);
    }

    /**
     * Update a unit tenant's details.
     */
    public function updateTenant(UpdateTenantRequest $request, Estate $estate, Unit $unit, Tenant $tenant): array
    {
        return $this->service->updateTenant($unit, $tenant, $request->validated());
    }

    /**
     * Move out the current tenant (sets is_active = false, unit becomes vacant).
     */
    public function moveOutTenant(MoveOutTenantRequest $request, Estate $estate, Unit $unit, Tenant $tenant): array
    {
        return $this->service->moveOutTenant($unit, $tenant, $request->validated());
    }

    /**
     * Delete a single unit tenant record.
     */
    public function deleteTenant(DeleteTenantRequest $request, Estate $estate, Unit $unit, Tenant $tenant): array
    {
        return $this->service->deleteTenant($unit, $tenant);
    }

    /**
     * Reinstate an inactive tenant (returns them to active without losing their existing data).
     */
    public function reinstateTenant(ReinstateTenantRequest $request, Estate $estate, Unit $unit, Tenant $tenant): array
    {
        return $this->service->reinstateTenant($unit, $tenant);
    }

    /**
     * Upload or replace the lease document for a unit tenant.
     */
    public function uploadLeaseDocument(UploadLeaseDocumentRequest $request, Estate $estate, Unit $unit, Tenant $tenant): array
    {
        return $this->service->uploadLeaseDocument($unit, $tenant, $request->file('lease_document'));
    }

    /**
     * Delete the lease document for a unit tenant.
     */
    public function deleteLeaseDocument(DeleteLeaseDocumentRequest $request, Estate $estate, Unit $unit, Tenant $tenant): array
    {
        return $this->service->deleteLeaseDocument($unit, $tenant);
    }
}
