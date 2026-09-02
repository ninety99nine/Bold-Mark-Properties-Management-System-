<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Unit;
use App\Models\UnitLedgerConfig;
use Illuminate\Http\JsonResponse;
use App\Services\UnitLedgerConfigService;
use App\Http\Resources\UnitLedgerConfigResource;
use App\Http\Resources\UnitLedgerConfigResources;
use App\Http\Requests\UnitLedgerConfig\ShowUnitLedgerConfigsRequest;
use App\Http\Requests\UnitLedgerConfig\CreateUnitLedgerConfigRequest;
use App\Http\Requests\UnitLedgerConfig\ShowUnitLedgerConfigRequest;
use App\Http\Requests\UnitLedgerConfig\UpdateUnitLedgerConfigRequest;
use App\Http\Requests\UnitLedgerConfig\DeleteUnitLedgerConfigRequest;
use App\Http\Requests\UnitLedgerConfig\DeleteUnitLedgerConfigsRequest;

class UnitLedgerConfigController extends Controller
{
    protected UnitLedgerConfigService $service;

    public function __construct(UnitLedgerConfigService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of ledger configurations for a unit.
     *
     * @param ShowUnitLedgerConfigsRequest $request
     * @param Unit                         $unit
     * @return UnitLedgerConfigResources
     */
    public function showUnitLedgerConfigs(ShowUnitLedgerConfigsRequest $request, Community $community, Unit $unit): UnitLedgerConfigResources
    {
        return $this->service->showUnitLedgerConfigs($unit, $request->validated());
    }

    /**
     * Create a new per-unit recurring ledger configuration.
     *
     * @param CreateUnitLedgerConfigRequest $request
     * @param Unit                          $unit
     * @return array
     */
    public function createUnitLedgerConfig(CreateUnitLedgerConfigRequest $request, Community $community, Unit $unit): JsonResponse
    {
        return response()->json($this->service->createUnitLedgerConfig($unit, $request->validated()), 201);
    }

    /**
     * Bulk delete unit ledger configs.
     *
     * @param DeleteUnitLedgerConfigsRequest $request
     * @param Unit                           $unit
     * @return array
     */
    public function deleteUnitLedgerConfigs(DeleteUnitLedgerConfigsRequest $request, Community $community, Unit $unit): array
    {
        return $this->service->deleteUnitLedgerConfigs($unit, $request->input('ledger_config_ids', []));
    }

    /**
     * Return a single unit ledger config.
     *
     * @param ShowUnitLedgerConfigRequest $request
     * @param Unit                        $unit
     * @param UnitLedgerConfig            $unitLedgerConfig
     * @return UnitLedgerConfigResource
     */
    public function showUnitLedgerConfig(ShowUnitLedgerConfigRequest $request, Community $community, Unit $unit, UnitLedgerConfig $unitLedgerConfig): UnitLedgerConfigResource
    {
        return $this->service->showUnitLedgerConfig($unit, $unitLedgerConfig);
    }

    /**
     * Update a unit ledger config.
     *
     * @param UpdateUnitLedgerConfigRequest $request
     * @param Unit                          $unit
     * @param UnitLedgerConfig              $unitLedgerConfig
     * @return array
     */
    public function updateUnitLedgerConfig(UpdateUnitLedgerConfigRequest $request, Community $community, Unit $unit, UnitLedgerConfig $unitLedgerConfig): array
    {
        return $this->service->updateUnitLedgerConfig($unit, $unitLedgerConfig, $request->validated());
    }

    /**
     * Delete a single unit ledger config.
     *
     * @param DeleteUnitLedgerConfigRequest $request
     * @param Unit                          $unit
     * @param UnitLedgerConfig              $unitLedgerConfig
     * @return array
     */
    public function deleteUnitLedgerConfig(DeleteUnitLedgerConfigRequest $request, Community $community, Unit $unit, UnitLedgerConfig $unitLedgerConfig): array
    {
        return $this->service->deleteUnitLedgerConfig($unit, $unitLedgerConfig);
    }
}
