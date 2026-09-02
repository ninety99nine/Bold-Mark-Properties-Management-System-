<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Services\LedgerService;
use App\Http\Resources\LedgerResource;
use App\Http\Resources\LedgerResources;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Ledger\ShowLedgersRequest;
use App\Http\Requests\Ledger\CreateLedgerRequest;
use App\Http\Requests\Ledger\ShowLedgerRequest;
use App\Http\Requests\Ledger\UpdateLedgerRequest;
use App\Http\Requests\Ledger\DeleteLedgerRequest;
use App\Http\Requests\Ledger\DeleteLedgersRequest;

class LedgerController extends Controller
{
    protected LedgerService $service;

    public function __construct(LedgerService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of ledgers for the authenticated occupant.
     *
     * @param ShowLedgersRequest $request
     * @return LedgerResources
     */
    public function showLedgers(ShowLedgersRequest $request): LedgerResources
    {
        return $this->service->showLedgers($request->validated());
    }

    /**
     * Create a new custom ledger.
     *
     * @param CreateLedgerRequest $request
     * @return JsonResponse
     */
    public function createLedger(CreateLedgerRequest $request): JsonResponse
    {
        return response()->json($this->service->createLedger($request->validated()), 201);
    }

    /**
     * Bulk delete ledgers (system types are skipped automatically).
     *
     * @param DeleteLedgersRequest $request
     * @return array
     */
    public function deleteLedgers(DeleteLedgersRequest $request): array
    {
        return $this->service->deleteLedgers($request->input('ledger_ids', []));
    }

    /**
     * Return a single ledger.
     *
     * @param ShowLedgerRequest $request
     * @param Ledger            $ledger
     * @return LedgerResource
     */
    public function showLedger(ShowLedgerRequest $request, Ledger $ledger): LedgerResource
    {
        return $this->service->showLedger($ledger);
    }

    /**
     * Update a ledger.
     * System types may only have name, description, and sort_order changed.
     *
     * @param UpdateLedgerRequest $request
     * @param Ledger              $ledger
     * @return array
     */
    public function updateLedger(UpdateLedgerRequest $request, Ledger $ledger): array
    {
        return $this->service->updateLedger($ledger, $request->validated());
    }

    /**
     * Delete a single ledger (system types will throw an exception).
     *
     * @param DeleteLedgerRequest $request
     * @param Ledger              $ledger
     * @return array
     */
    public function deleteLedger(DeleteLedgerRequest $request, Ledger $ledger): array
    {
        return $this->service->deleteLedger($ledger);
    }
}
