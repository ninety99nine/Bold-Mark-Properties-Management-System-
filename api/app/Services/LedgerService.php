<?php

namespace App\Services;

use Exception;
use App\Models\Ledger;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\LedgerResource;
use App\Http\Resources\LedgerResources;

class LedgerService extends BaseService
{
    protected array $allowedRelationships = ['communities'];
    /**
     * Return a paginated, filtered list of ledgers for the authenticated occupant.
     *
     * @param array $data
     * @return LedgerResources
     */
    public function showLedgers(array $data): LedgerResources
    {
        $user  = Auth::user();
        $query = Ledger::where('organization_id', $user->organization_id);

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
     * Create a new custom ledger for the authenticated occupant.
     *
     * @param array $data
     * @return array
     */
    public function createLedger(array $data): array
    {
        $user = Auth::user();

        $ledger = Ledger::create(array_merge(
            collect($data)->only(['name', 'description', 'applies_to', 'is_recurring', 'is_active', 'sort_order'])->toArray(),
            [
                'organization_id' => $user->organization_id,
                'is_system' => false,
                'is_active' => $data['is_active'] ?? true,
            ]
        ));

        return $this->showCreatedResource($ledger);
    }

    /**
     * Bulk delete ledgers by an array of IDs (skips system types).
     *
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteLedgers(array $ids): array
    {
        $user        = Auth::user();
        $ledgers = Ledger::whereIn('id', $ids)
            ->where('organization_id', $user->organization_id)
            ->where('is_system', false)  // Never delete system types in bulk
            ->get();

        $total = $ledgers->count();

        if ($total === 0) {
            throw new Exception('No Ledgers deleted (system types cannot be deleted)');
        }

        foreach ($ledgers as $ledger) {
            $ledger->delete();
        }

        $label = $total === 1 ? 'Ledger' : 'Ledgers';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Return a single ledger resource.
     *
     * @param Ledger $ledger
     * @return LedgerResource
     */
    public function showLedger(Ledger $ledger): LedgerResource
    {
        return $this->showResource($ledger);
    }

    /**
     * Update a ledger.
     * System types (Levy, Rent) may only have name, description, and sort_order changed.
     *
     * @param Ledger $ledger
     * @param array      $data
     * @return array
     */
    public function updateLedger(Ledger $ledger, array $data): array
    {
        if ($ledger->is_system) {
            // System types: only allow safe cosmetic fields
            $updateData = collect($data)
                ->only(['name', 'description', 'sort_order'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();
        } else {
            $updateData = collect($data)
                ->only(['name', 'description', 'applies_to', 'is_recurring', 'is_active', 'sort_order'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();
        }

        $ledger->update($updateData);

        return $this->showUpdatedResource($ledger);
    }

    /**
     * Delete a single ledger (system types cannot be deleted).
     *
     * @param Ledger $ledger
     * @return array
     * @throws Exception
     */
    public function deleteLedger(Ledger $ledger): array
    {
        if ($ledger->is_system) {
            throw new Exception('System ledgers cannot be deleted');
        }

        $deleted = $ledger->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Ledger deleted' : 'Ledger delete unsuccessful',
        ];
    }
}
