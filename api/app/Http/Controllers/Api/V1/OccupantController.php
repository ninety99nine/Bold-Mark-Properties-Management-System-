<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Unit;
use App\Models\Occupant;
use App\Services\OccupantService;
use App\Http\Resources\OccupantResource;
use App\Http\Resources\OccupantResources;
use App\Http\Requests\Occupant\ShowOccupantsRequest;
use App\Http\Requests\Occupant\CreateOccupantRequest;
use App\Http\Requests\Occupant\ShowOccupantRequest;
use App\Http\Requests\Occupant\UpdateOccupantRequest;
use App\Http\Requests\Occupant\MoveOutOccupantRequest;
use App\Http\Requests\Occupant\DeleteOccupantRequest;
use App\Http\Requests\Occupant\DeleteOccupantsRequest;
use App\Http\Requests\Occupant\UploadLeaseDocumentRequest;
use App\Http\Requests\Occupant\DeleteLeaseDocumentRequest;
use App\Http\Requests\Occupant\ReinstateOccupantRequest;

class OccupantController extends Controller
{
    protected OccupantService $service;

    public function __construct(OccupantService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of occupant history for a unit.
     */
    public function showOccupants(ShowOccupantsRequest $request, Community $community, Unit $unit): OccupantResources
    {
        return $this->service->showOccupants($unit, $request->validated());
    }

    /**
     * Move in a new occupant (creates a unit occupant record and updates unit occupancy).
     */
    public function createOccupant(CreateOccupantRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->createOccupant($unit, $request->validated());
    }

    /**
     * Bulk delete unit occupant records.
     */
    public function deleteOccupants(DeleteOccupantsRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->deleteOccupants($unit, $request->input('occupant_ids', []));
    }

    /**
     * Return a single unit occupant record.
     */
    public function showOccupant(ShowOccupantRequest $request, Community $community, Unit $unit, Occupant $occupant): OccupantResource
    {
        return $this->service->showOccupant($unit, $occupant);
    }

    /**
     * Update a unit occupant's details.
     */
    public function updateOccupant(UpdateOccupantRequest $request, Community $community, Unit $unit, Occupant $occupant): array
    {
        return $this->service->updateOccupant($unit, $occupant, $request->validated());
    }

    /**
     * Move out the current occupant (sets is_active = false, unit becomes vacant).
     */
    public function moveOutOccupant(MoveOutOccupantRequest $request, Community $community, Unit $unit, Occupant $occupant): array
    {
        return $this->service->moveOutOccupant($unit, $occupant, $request->validated());
    }

    /**
     * Delete a single unit occupant record.
     */
    public function deleteOccupant(DeleteOccupantRequest $request, Community $community, Unit $unit, Occupant $occupant): array
    {
        return $this->service->deleteOccupant($unit, $occupant);
    }

    /**
     * Reinstate an inactive occupant (returns them to active without losing their existing data).
     */
    public function reinstateOccupant(ReinstateOccupantRequest $request, Community $community, Unit $unit, Occupant $occupant): array
    {
        return $this->service->reinstateOccupant($unit, $occupant);
    }

    /**
     * Upload or replace the lease document for a unit occupant.
     */
    public function uploadLeaseDocument(UploadLeaseDocumentRequest $request, Community $community, Unit $unit, Occupant $occupant): array
    {
        return $this->service->uploadLeaseDocument($unit, $occupant, $request->file('lease_document'));
    }

    /**
     * Delete the lease document for a unit occupant.
     */
    public function deleteLeaseDocument(DeleteLeaseDocumentRequest $request, Community $community, Unit $unit, Occupant $occupant): array
    {
        return $this->service->deleteLeaseDocument($unit, $occupant);
    }
}
