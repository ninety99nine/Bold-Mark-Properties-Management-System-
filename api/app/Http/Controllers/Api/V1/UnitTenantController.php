<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Estate;
use App\Models\Unit;
use App\Models\UnitTenant;
use App\Services\UnitTenantService;
use App\Http\Resources\UnitTenantResource;
use App\Http\Resources\UnitTenantResources;
use App\Http\Requests\UnitTenant\ShowUnitTenantsRequest;
use App\Http\Requests\UnitTenant\CreateUnitTenantRequest;
use App\Http\Requests\UnitTenant\ShowUnitTenantRequest;
use App\Http\Requests\UnitTenant\UpdateUnitTenantRequest;
use App\Http\Requests\UnitTenant\MoveOutUnitTenantRequest;
use App\Http\Requests\UnitTenant\DeleteUnitTenantRequest;
use App\Http\Requests\UnitTenant\DeleteUnitTenantsRequest;
use App\Http\Requests\UnitTenant\UploadLeaseDocumentRequest;
use App\Http\Requests\UnitTenant\DeleteLeaseDocumentRequest;
use App\Http\Requests\UnitTenant\ReinstateUnitTenantRequest;

class UnitTenantController extends Controller
{
    protected UnitTenantService $service;

    public function __construct(UnitTenantService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of tenant history for a unit.
     */
    public function showUnitTenants(ShowUnitTenantsRequest $request, Estate $estate, Unit $unit): UnitTenantResources
    {
        return $this->service->showUnitTenants($unit, $request->validated());
    }

    /**
     * Move in a new tenant (creates a unit tenant record and updates unit occupancy).
     */
    public function createUnitTenant(CreateUnitTenantRequest $request, Estate $estate, Unit $unit): array
    {
        return $this->service->createUnitTenant($unit, $request->validated());
    }

    /**
     * Bulk delete unit tenant records.
     */
    public function deleteUnitTenants(DeleteUnitTenantsRequest $request, Estate $estate, Unit $unit): array
    {
        return $this->service->deleteUnitTenants($unit, $request->input('unit_tenant_ids', []));
    }

    /**
     * Return a single unit tenant record.
     */
    public function showUnitTenant(ShowUnitTenantRequest $request, Estate $estate, Unit $unit, UnitTenant $unitTenant): UnitTenantResource
    {
        return $this->service->showUnitTenant($unit, $unitTenant);
    }

    /**
     * Update a unit tenant's details.
     */
    public function updateUnitTenant(UpdateUnitTenantRequest $request, Estate $estate, Unit $unit, UnitTenant $unitTenant): array
    {
        return $this->service->updateUnitTenant($unit, $unitTenant, $request->validated());
    }

    /**
     * Move out the current tenant (sets is_active = false, unit becomes vacant).
     */
    public function moveOutUnitTenant(MoveOutUnitTenantRequest $request, Estate $estate, Unit $unit, UnitTenant $unitTenant): array
    {
        return $this->service->moveOutUnitTenant($unit, $unitTenant, $request->validated());
    }

    /**
     * Delete a single unit tenant record.
     */
    public function deleteUnitTenant(DeleteUnitTenantRequest $request, Estate $estate, Unit $unit, UnitTenant $unitTenant): array
    {
        return $this->service->deleteUnitTenant($unit, $unitTenant);
    }

    /**
     * Reinstate an inactive tenant (returns them to active without losing their existing data).
     */
    public function reinstateUnitTenant(ReinstateUnitTenantRequest $request, Estate $estate, Unit $unit, UnitTenant $unitTenant): array
    {
        return $this->service->reinstateUnitTenant($unit, $unitTenant);
    }

    /**
     * Upload or replace the lease document for a unit tenant.
     */
    public function uploadLeaseDocument(UploadLeaseDocumentRequest $request, Estate $estate, Unit $unit, UnitTenant $unitTenant): array
    {
        return $this->service->uploadLeaseDocument($unit, $unitTenant, $request->file('lease_document'));
    }

    /**
     * Delete the lease document for a unit tenant.
     */
    public function deleteLeaseDocument(DeleteLeaseDocumentRequest $request, Estate $estate, Unit $unit, UnitTenant $unitTenant): array
    {
        return $this->service->deleteLeaseDocument($unit, $unitTenant);
    }
}
