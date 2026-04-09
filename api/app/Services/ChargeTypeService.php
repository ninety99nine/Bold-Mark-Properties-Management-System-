<?php

namespace App\Services;

use Exception;
use App\Models\ChargeType;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ChargeTypeResource;
use App\Http\Resources\ChargeTypeResources;

class ChargeTypeService extends BaseService
{
    /**
     * Return a paginated, filtered list of charge types for the authenticated tenant.
     *
     * @param array $data
     * @return ChargeTypeResources
     */
    public function showChargeTypes(array $data): ChargeTypeResources
    {
        $user  = Auth::user();
        $query = ChargeType::where('tenant_id', $user->tenant_id);

        if (!empty($data['applies_to'])) {
            $query->where('applies_to', $data['applies_to']);
        }

        if (isset($data['is_recurring'])) {
            $isRecurring = $data['is_recurring'] === 'true' || $data['is_recurring'] === true || $data['is_recurring'] === 1;
            $query->where('is_recurring', $isRecurring);
        }

        if (isset($data['is_active'])) {
            $isActive = $data['is_active'] === 'true' || $data['is_active'] === true || $data['is_active'] === 1;
            $query->where('is_active', $isActive);
        }

        if (isset($data['is_system'])) {
            $isSystem = $data['is_system'] === 'true' || $data['is_system'] === true || $data['is_system'] === 1;
            $query->where('is_system', $isSystem);
        }

        // Default sort by sort_order then name; override with _sort if provided
        if (!request()->has('_sort')) {
            $query = $query->orderBy('sort_order')->orderBy('name');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a new custom charge type for the authenticated tenant.
     *
     * @param array $data
     * @return array
     */
    public function createChargeType(array $data): array
    {
        $user = Auth::user();

        $chargeType = ChargeType::create(array_merge(
            collect($data)->only(['code', 'name', 'description', 'applies_to', 'is_recurring', 'is_active', 'sort_order'])->toArray(),
            [
                'tenant_id' => $user->tenant_id,
                'is_system' => false,
                'is_active' => $data['is_active'] ?? true,
            ]
        ));

        return $this->showCreatedResource($chargeType);
    }

    /**
     * Bulk delete charge types by an array of IDs (skips system types).
     *
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteChargeTypes(array $ids): array
    {
        $user        = Auth::user();
        $chargeTypes = ChargeType::whereIn('id', $ids)
            ->where('tenant_id', $user->tenant_id)
            ->where('is_system', false)  // Never delete system types in bulk
            ->get();

        $total = $chargeTypes->count();

        if ($total === 0) {
            throw new Exception('No Charge Types deleted (system types cannot be deleted)');
        }

        foreach ($chargeTypes as $chargeType) {
            $chargeType->delete();
        }

        $label = $total === 1 ? 'Charge Type' : 'Charge Types';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Return a single charge type resource.
     *
     * @param ChargeType $chargeType
     * @return ChargeTypeResource
     */
    public function showChargeType(ChargeType $chargeType): ChargeTypeResource
    {
        return $this->showResource($chargeType);
    }

    /**
     * Update a charge type.
     * System types (Levy, Rent) may only have name, description, and sort_order changed.
     *
     * @param ChargeType $chargeType
     * @param array      $data
     * @return array
     */
    public function updateChargeType(ChargeType $chargeType, array $data): array
    {
        if ($chargeType->is_system) {
            // System types: only allow safe cosmetic fields
            $updateData = collect($data)
                ->only(['name', 'description', 'sort_order'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();
        } else {
            $updateData = collect($data)
                ->only(['name', 'code', 'description', 'applies_to', 'is_recurring', 'is_active', 'sort_order'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();
        }

        $chargeType->update($updateData);

        return $this->showUpdatedResource($chargeType);
    }

    /**
     * Delete a single charge type (system types cannot be deleted).
     *
     * @param ChargeType $chargeType
     * @return array
     * @throws Exception
     */
    public function deleteChargeType(ChargeType $chargeType): array
    {
        if ($chargeType->is_system) {
            throw new Exception('System charge types cannot be deleted');
        }

        $deleted = $chargeType->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Charge Type deleted' : 'Charge Type delete unsuccessful',
        ];
    }
}
