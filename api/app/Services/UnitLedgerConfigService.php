<?php

namespace App\Services;

use Exception;
use App\Models\Unit;
use App\Models\UnitLedgerConfig;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\UnitLedgerConfigResource;
use App\Http\Resources\UnitLedgerConfigResources;

class UnitLedgerConfigService extends BaseService
{
    protected array $allowedRelationships = ['unit', 'ledger'];

    /**
     * Return a paginated list of ledger configurations for the given unit.
     *
     * @param Unit  $unit
     * @param array $data
     * @return UnitLedgerConfigResources
     */
    public function showUnitLedgerConfigs(Unit $unit, array $data): UnitLedgerConfigResources
    {
        $query = UnitLedgerConfig::where('unit_id', $unit->id)
            ->with('ledger');

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
     * Create a new per-unit recurring ledger configuration.
     *
     * @param Unit  $unit
     * @param array $data
     * @return array
     */
    public function createUnitLedgerConfig(Unit $unit, array $data): array
    {
        $configData = collect($data)
            ->only(['ledger_id', 'amount', 'is_active'])
            ->toArray();

        $config = UnitLedgerConfig::create(array_merge($configData, [
            'unit_id'   => $unit->id,
            'is_active' => $data['is_active'] ?? true,
        ]));

        return $this->showCreatedResource($config);
    }

    /**
     * Return a single unit ledger config resource.
     *
     * @param Unit             $unit
     * @param UnitLedgerConfig $config
     * @return UnitLedgerConfigResource
     */
    public function showUnitLedgerConfig(Unit $unit, UnitLedgerConfig $config): UnitLedgerConfigResource
    {
        return $this->showResource($config);
    }

    /**
     * Update a unit ledger configuration.
     *
     * @param Unit             $unit
     * @param UnitLedgerConfig $config
     * @param array            $data
     * @return array
     */
    public function updateUnitLedgerConfig(Unit $unit, UnitLedgerConfig $config, array $data): array
    {
        $updateData = collect($data)
            ->only(['amount', 'is_active'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $config->update($updateData);

        return $this->showUpdatedResource($config);
    }

    /**
     * Bulk delete unit ledger configs by an array of IDs.
     *
     * @param Unit  $unit
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteUnitLedgerConfigs(Unit $unit, array $ids): array
    {
        $configs = UnitLedgerConfig::whereIn('id', $ids)
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
     * Delete a single unit ledger config.
     *
     * @param Unit             $unit
     * @param UnitLedgerConfig $config
     * @return array
     */
    public function deleteUnitLedgerConfig(Unit $unit, UnitLedgerConfig $config): array
    {
        $deleted = $config->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Charge Config deleted' : 'Charge Config delete unsuccessful',
        ];
    }
}
