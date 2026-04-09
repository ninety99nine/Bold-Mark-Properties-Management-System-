<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitChargeConfig;
use App\Services\UnitChargeConfigService;
use App\Http\Resources\UnitChargeConfigResource;
use App\Http\Resources\UnitChargeConfigResources;
use App\Http\Requests\UnitChargeConfig\ShowUnitChargeConfigsRequest;
use App\Http\Requests\UnitChargeConfig\CreateUnitChargeConfigRequest;
use App\Http\Requests\UnitChargeConfig\ShowUnitChargeConfigRequest;
use App\Http\Requests\UnitChargeConfig\UpdateUnitChargeConfigRequest;
use App\Http\Requests\UnitChargeConfig\DeleteUnitChargeConfigRequest;
use App\Http\Requests\UnitChargeConfig\DeleteUnitChargeConfigsRequest;

class UnitChargeConfigController extends Controller
{
    protected UnitChargeConfigService $service;

    public function __construct(UnitChargeConfigService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of charge configurations for a unit.
     *
     * @param ShowUnitChargeConfigsRequest $request
     * @param Unit                         $unit
     * @return UnitChargeConfigResources
     */
    public function showUnitChargeConfigs(ShowUnitChargeConfigsRequest $request, Unit $unit): UnitChargeConfigResources
    {
        return $this->service->showUnitChargeConfigs($unit, $request->validated());
    }

    /**
     * Create a new per-unit recurring charge configuration.
     *
     * @param CreateUnitChargeConfigRequest $request
     * @param Unit                          $unit
     * @return array
     */
    public function createUnitChargeConfig(CreateUnitChargeConfigRequest $request, Unit $unit): array
    {
        return $this->service->createUnitChargeConfig($unit, $request->validated());
    }

    /**
     * Bulk delete unit charge configs.
     *
     * @param DeleteUnitChargeConfigsRequest $request
     * @param Unit                           $unit
     * @return array
     */
    public function deleteUnitChargeConfigs(DeleteUnitChargeConfigsRequest $request, Unit $unit): array
    {
        return $this->service->deleteUnitChargeConfigs($unit, $request->input('unit_charge_config_ids', []));
    }

    /**
     * Return a single unit charge config.
     *
     * @param ShowUnitChargeConfigRequest $request
     * @param Unit                        $unit
     * @param UnitChargeConfig            $unitChargeConfig
     * @return UnitChargeConfigResource
     */
    public function showUnitChargeConfig(ShowUnitChargeConfigRequest $request, Unit $unit, UnitChargeConfig $unitChargeConfig): UnitChargeConfigResource
    {
        return $this->service->showUnitChargeConfig($unit, $unitChargeConfig);
    }

    /**
     * Update a unit charge config.
     *
     * @param UpdateUnitChargeConfigRequest $request
     * @param Unit                          $unit
     * @param UnitChargeConfig              $unitChargeConfig
     * @return array
     */
    public function updateUnitChargeConfig(UpdateUnitChargeConfigRequest $request, Unit $unit, UnitChargeConfig $unitChargeConfig): array
    {
        return $this->service->updateUnitChargeConfig($unit, $unitChargeConfig, $request->validated());
    }

    /**
     * Delete a single unit charge config.
     *
     * @param DeleteUnitChargeConfigRequest $request
     * @param Unit                          $unit
     * @param UnitChargeConfig              $unitChargeConfig
     * @return array
     */
    public function deleteUnitChargeConfig(DeleteUnitChargeConfigRequest $request, Unit $unit, UnitChargeConfig $unitChargeConfig): array
    {
        return $this->service->deleteUnitChargeConfig($unit, $unitChargeConfig);
    }
}
