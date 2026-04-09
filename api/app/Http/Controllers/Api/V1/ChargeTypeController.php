<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChargeType;
use App\Services\ChargeTypeService;
use App\Http\Resources\ChargeTypeResource;
use App\Http\Resources\ChargeTypeResources;
use App\Http\Requests\ChargeType\ShowChargeTypesRequest;
use App\Http\Requests\ChargeType\CreateChargeTypeRequest;
use App\Http\Requests\ChargeType\ShowChargeTypeRequest;
use App\Http\Requests\ChargeType\UpdateChargeTypeRequest;
use App\Http\Requests\ChargeType\DeleteChargeTypeRequest;
use App\Http\Requests\ChargeType\DeleteChargeTypesRequest;

class ChargeTypeController extends Controller
{
    protected ChargeTypeService $service;

    public function __construct(ChargeTypeService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of charge types for the authenticated tenant.
     *
     * @param ShowChargeTypesRequest $request
     * @return ChargeTypeResources
     */
    public function showChargeTypes(ShowChargeTypesRequest $request): ChargeTypeResources
    {
        return $this->service->showChargeTypes($request->validated());
    }

    /**
     * Create a new custom charge type.
     *
     * @param CreateChargeTypeRequest $request
     * @return array
     */
    public function createChargeType(CreateChargeTypeRequest $request): array
    {
        return $this->service->createChargeType($request->validated());
    }

    /**
     * Bulk delete charge types (system types are skipped automatically).
     *
     * @param DeleteChargeTypesRequest $request
     * @return array
     */
    public function deleteChargeTypes(DeleteChargeTypesRequest $request): array
    {
        return $this->service->deleteChargeTypes($request->input('charge_type_ids', []));
    }

    /**
     * Return a single charge type.
     *
     * @param ShowChargeTypeRequest $request
     * @param ChargeType            $chargeType
     * @return ChargeTypeResource
     */
    public function showChargeType(ShowChargeTypeRequest $request, ChargeType $chargeType): ChargeTypeResource
    {
        return $this->service->showChargeType($chargeType);
    }

    /**
     * Update a charge type.
     * System types may only have name, description, and sort_order changed.
     *
     * @param UpdateChargeTypeRequest $request
     * @param ChargeType              $chargeType
     * @return array
     */
    public function updateChargeType(UpdateChargeTypeRequest $request, ChargeType $chargeType): array
    {
        return $this->service->updateChargeType($chargeType, $request->validated());
    }

    /**
     * Delete a single charge type (system types will throw an exception).
     *
     * @param DeleteChargeTypeRequest $request
     * @param ChargeType              $chargeType
     * @return array
     */
    public function deleteChargeType(DeleteChargeTypeRequest $request, ChargeType $chargeType): array
    {
        return $this->service->deleteChargeType($chargeType);
    }
}
