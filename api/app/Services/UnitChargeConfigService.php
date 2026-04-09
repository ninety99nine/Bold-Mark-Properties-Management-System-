<?php

namespace App\Services;

use Exception;
use App\Models\Unit;
use App\Models\UnitChargeConfig;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\UnitChargeConfigResource;
use App\Http\Resources\UnitChargeConfigResources;

class UnitChargeConfigService extends BaseService
{
    /**
     * Return a paginated list of charge configurations for the given unit.
     *
     * @param Unit  $unit
     * @param array $data
     * @return UnitChargeConfigResources
     */
    public function showUnitChargeConfigs(Unit $unit, array $data): UnitChargeConfigResources
    {
        $query = UnitChargeConfig::where('unit_id', $unit->id)
            ->with('chargeType');

        if (isset($data['is_active'])) {
            $isActive = $data['is_active'] === 'true' || $data['is_active'] === true || $data['is_active'] === 1;
            $query->where('is_active', $isActive);
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a new per-unit recurring charge configuration.
     *
     * @param Unit  $unit
     * @param array $data
     * @return array
     */
    public function createUnitChargeConfig(Unit $unit, array $data): array
    {
        $configData = collect($data)
            ->only(['charge_type_id', 'amount', 'is_active'])
            ->toArray();

        $config = UnitChargeConfig::create(array_merge($configData, [
            'unit_id'   => $unit->id,
            'is_active' => $data['is_active'] ?? true,
        ]));

        return $this->showCreatedResource($config);
    }

    /**
     * Return a single unit charge config resource.
     *
     * @param Unit             $unit
     * @param UnitChargeConfig $config
     * @return UnitChargeConfigResource
     */
    public function showUnitChargeConfig(Unit $unit, UnitChargeConfig $config): UnitChargeConfigResource
    {
        return $this->showResource($config);
    }

    /**
     * Update a unit charge configuration.
     *
     * @param Unit             $unit
     * @param UnitChargeConfig $config
     * @param array            $data
     * @return array
     */
    public function updateUnitChargeConfig(Unit $unit, UnitChargeConfig $config, array $data): array
    {
        $updateData = collect($data)
            ->only(['amount', 'is_active'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $config->update($updateData);

        return $this->showUpdatedResource($config);
    }

    /**
     * Bulk delete unit charge configs by an array of IDs.
     *
     * @param Unit  $unit
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteUnitChargeConfigs(Unit $unit, array $ids): array
    {
        $configs = UnitChargeConfig::whereIn('id', $ids)
            ->where('unit_id', $unit->id)
            ->get();

        $total = $configs->count();

        if ($total === 0) {
            throw new Exception('No Charge Configs deleted');
        }

        foreach ($configs as $config) {
            $config->delete();
        }

        $label = $total === 1 ? 'Charge Config' : 'Charge Configs';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single unit charge config.
     *
     * @param Unit             $unit
     * @param UnitChargeConfig $config
     * @return array
     */
    public function deleteUnitChargeConfig(Unit $unit, UnitChargeConfig $config): array
    {
        $deleted = $config->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Charge Config deleted' : 'Charge Config delete unsuccessful',
        ];
    }
}
