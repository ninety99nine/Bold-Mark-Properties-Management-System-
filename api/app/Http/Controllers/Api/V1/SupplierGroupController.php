<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplierGroup;
use App\Services\SupplierGroupService;
use App\Http\Resources\SupplierGroupResource;
use App\Http\Resources\SupplierGroupResources;
use App\Http\Requests\SupplierGroup\ShowSupplierGroupsRequest;
use App\Http\Requests\SupplierGroup\ShowSupplierGroupRequest;
use App\Http\Requests\SupplierGroup\CreateSupplierGroupRequest;
use App\Http\Requests\SupplierGroup\DeleteSupplierGroupRequest;

class SupplierGroupController extends Controller
{
    /**
     * @var SupplierGroupService
     */
    protected $service;

    /**
     * SupplierGroupController constructor.
     *
     * @param SupplierGroupService $service
     */
    public function __construct(SupplierGroupService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of supplier groups for the authenticated user's organization.
     *
     * @param ShowSupplierGroupsRequest $request
     * @return SupplierGroupResources|array
     */
    public function showSupplierGroups(ShowSupplierGroupsRequest $request): SupplierGroupResources|array
    {
        return $this->service->showSupplierGroups($request->validated());
    }

    /**
     * Create a supplier group.
     *
     * @param CreateSupplierGroupRequest $request
     * @return array
     */
    public function createSupplierGroup(CreateSupplierGroupRequest $request): array
    {
        return $this->service->createSupplierGroup($request->validated());
    }

    /**
     * Return a single supplier group.
     *
     * @param ShowSupplierGroupRequest $request
     * @param SupplierGroup $supplierGroup
     * @return SupplierGroupResource
     */
    public function showSupplierGroup(ShowSupplierGroupRequest $request, SupplierGroup $supplierGroup): SupplierGroupResource
    {
        return $this->service->showSupplierGroup($supplierGroup);
    }

    /**
     * Delete a single supplier group.
     *
     * @param DeleteSupplierGroupRequest $request
     * @param SupplierGroup $supplierGroup
     * @return array
     */
    public function deleteSupplierGroup(DeleteSupplierGroupRequest $request, SupplierGroup $supplierGroup): array
    {
        return $this->service->deleteSupplierGroup($supplierGroup);
    }
}
