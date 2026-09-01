<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Unit;
use App\Models\UnitCollectionNote;
use App\Services\CustomerNoteService;
use App\Http\Requests\CustomerNote\ShowCustomerNotesRequest;
use App\Http\Requests\CustomerNote\StoreCustomerNoteRequest;
use App\Http\Requests\CustomerNote\UpdateCustomerNoteRequest;
use App\Http\Requests\CustomerNote\PhonecallRequest;

class CustomerNoteController extends Controller
{
    /**
     * @var CustomerNoteService
     */
    protected $service;

    /**
     * CustomerNoteController constructor.
     *
     * @param CustomerNoteService $service
     */
    public function __construct(CustomerNoteService $service)
    {
        $this->service = $service;
    }

    /**
     * List the customer notes for a unit.
     *
     * @param ShowCustomerNotesRequest $request
     * @param Community $community
     * @param Unit $unit
     * @return array
     */
    public function index(ShowCustomerNotesRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->index($community, $unit);
    }

    /**
     * Store a new customer note (with an optional document).
     *
     * @param StoreCustomerNoteRequest $request
     * @param Community $community
     * @param Unit $unit
     * @return array
     */
    public function store(StoreCustomerNoteRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->store($community, $unit, $request->validated());
    }

    /**
     * Update an existing customer note.
     *
     * @param UpdateCustomerNoteRequest $request
     * @param Community $community
     * @param Unit $unit
     * @param UnitCollectionNote $note
     * @return array
     */
    public function update(UpdateCustomerNoteRequest $request, Community $community, Unit $unit, UnitCollectionNote $note): array
    {
        return $this->service->update($community, $unit, $note, $request->validated());
    }

    /**
     * Delete a customer note.
     *
     * @param ShowCustomerNotesRequest $request
     * @param Community $community
     * @param Unit $unit
     * @param UnitCollectionNote $note
     * @return array
     */
    public function destroy(ShowCustomerNotesRequest $request, Community $community, Unit $unit, UnitCollectionNote $note): array
    {
        return $this->service->destroy($community, $unit, $note);
    }

    /**
     * Log a customer phone call as a note.
     *
     * @param PhonecallRequest $request
     * @param Community $community
     * @param Unit $unit
     * @return array
     */
    public function phonecall(PhonecallRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->phonecall($community, $unit, $request->validated());
    }
}
