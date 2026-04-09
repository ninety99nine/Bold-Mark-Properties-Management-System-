<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\OwnerService;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\OwnerResources;
use App\Http\Requests\Owner\ShowOwnersRequest;
use App\Http\Requests\Owner\ShowOwnerRequest;
use App\Http\Requests\Owner\UpdateOwnerRequest;
use App\Http\Requests\Owner\DeleteOwnerRequest;
use App\Http\Requests\Owner\DeleteOwnersRequest;

class OwnerController extends Controller
{
    protected OwnerService $service;

    public function __construct(OwnerService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of owners for the authenticated tenant.
     * Owners are created via unit creation — no store endpoint.
     *
     * @param ShowOwnersRequest $request
     * @return OwnerResources
     */
    public function showOwners(ShowOwnersRequest $request): OwnerResources
    {
        return $this->service->showOwners($request->validated());
    }

    /**
     * Bulk delete owners.
     *
     * @param DeleteOwnersRequest $request
     * @return array
     */
    public function deleteOwners(DeleteOwnersRequest $request): array
    {
        return $this->service->deleteOwners($request->input('owner_ids', []));
    }

    /**
     * Return a single owner.
     *
     * @param ShowOwnerRequest $request
     * @param Owner            $owner
     * @return OwnerResource
     */
    public function showOwner(ShowOwnerRequest $request, Owner $owner): OwnerResource
    {
        return $this->service->showOwner($owner);
    }

    /**
     * Update an owner's details.
     *
     * @param UpdateOwnerRequest $request
     * @param Owner              $owner
     * @return array
     */
    public function updateOwner(UpdateOwnerRequest $request, Owner $owner): array
    {
        return $this->service->updateOwner($owner, $request->validated());
    }

    /**
     * Delete a single owner.
     *
     * @param DeleteOwnerRequest $request
     * @param Owner              $owner
     * @return array
     */
    public function deleteOwner(DeleteOwnerRequest $request, Owner $owner): array
    {
        return $this->service->deleteOwner($owner);
    }
}
