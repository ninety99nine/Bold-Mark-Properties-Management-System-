<?php

namespace App\Services;

use Exception;
use App\Models\Unit;
use App\Models\Occupant;
use App\Models\UnitActivity;
use App\Enums\OccupancyType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\OccupantResource;
use App\Http\Resources\OccupantResources;

class OccupantService extends BaseService
{
    /**
     * Return a paginated list of occupant history records for the given unit.
     *
     * @param Unit  $unit
     * @param array $data
     * @return OccupantResources
     */
    public function showOccupants(Unit $unit, array $data): OccupantResources
    {
        $query = Occupant::where('unit_id', $unit->id)->with('unit');

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
     * Move in a new occupant: deactivate any existing active occupant, create the new record,
     * and set the unit occupancy to occupant_occupied.
     *
     * @param Unit  $unit
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createOccupant(Unit $unit, array $data): array
    {
        $user = Auth::user();

        // Archive any existing active occupant
        Occupant::where('unit_id', $unit->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $occupantData = collect($data)
            ->only(['full_name', 'email', 'secondary_emails', 'phone', 'id_number', 'postal_address', 'car_registration', 'lease_start', 'lease_end', 'rent_amount'])
            ->toArray();

        $occupant = Occupant::create(array_merge($occupantData, [
            'unit_id'   => $unit->id,
            'organization_id' => $user->organization_id,
            'is_active' => true,
        ]));

        // Update unit occupancy type and rent amount if provided
        $unitUpdate = ['occupancy_type' => OccupancyType::OCCUPANT_OCCUPIED->value];

        if (!empty($data['rent_amount'])) {
            $unitUpdate['rent_amount'] = $data['rent_amount'];
        }

        $unit->update($unitUpdate);

        // Log move-in event
        $changes = array_filter([
            !empty($occupant->full_name)   ? ['field' => 'Full Name',   'old' => null, 'new' => $occupant->full_name]   : null,
            !empty($occupant->email)        ? ['field' => 'Email',       'old' => null, 'new' => $occupant->email]        : null,
            !empty($occupant->lease_start)  ? ['field' => 'Lease Start', 'old' => null, 'new' => (string) $occupant->lease_start] : null,
            !empty($occupant->lease_end)    ? ['field' => 'Lease End',   'old' => null, 'new' => (string) $occupant->lease_end]   : null,
            !empty($occupant->rent_amount)  ? ['field' => 'Monthly Rent','old' => null, 'new' => (string) $occupant->rent_amount] : null,
        ]);

        UnitActivity::create([
            'unit_id'         => $unit->id,
            'organization_id'       => $unit->organization_id,
            'user_id'         => $user?->id,
            'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'event'           => 'Moved in occupant',
            'category'        => 'occupant',
            'changes'         => array_values($changes),
        ]);

        return $this->showCreatedResource($occupant);
    }

    /**
     * Return a single unit occupant resource.
     *
     * @param Unit       $unit
     * @param Occupant $occupant
     * @return OccupantResource
     */
    public function showOccupant(Unit $unit, Occupant $occupant): OccupantResource
    {
        $occupant->loadMissing(['unit.community']);
        return $this->showResource($occupant);
    }

    /**
     * Update a unit occupant's details.
     *
     * @param Unit       $unit
     * @param Occupant $occupant
     * @param array      $data
     * @return array
     */
    public function updateOccupant(Unit $unit, Occupant $occupant, array $data): array
    {
        $occupantData = collect($data)
            ->only(['full_name', 'email', 'secondary_emails', 'phone', 'id_number', 'lease_start', 'lease_end', 'rent_amount',
                    'move_out_date', 'move_out_reason', 'move_out_notes'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $occupant->update($occupantData);

        // Keep unit rent_amount in sync if it was updated
        if (!empty($data['rent_amount'])) {
            $unit->update(['rent_amount' => $data['rent_amount']]);
        }

        return $this->showUpdatedResource($occupant);
    }

    /**
     * Move out the current occupant: set is_active to false and update unit to vacant.
     *
     * @param Unit       $unit
     * @param Occupant $occupant
     * @return array
     */
    public function moveOutOccupant(Unit $unit, Occupant $occupant, array $data = []): array
    {
        $user = Auth::user();

        $updateData = ['is_active' => false];

        if (!empty($data['move_out_date'])) {
            $updateData['move_out_date'] = $data['move_out_date'];
        }
        if (!empty($data['move_out_reason'])) {
            $updateData['move_out_reason'] = $data['move_out_reason'];
        }
        if (!empty($data['move_out_notes'])) {
            $updateData['move_out_notes'] = $data['move_out_notes'];
        }

        $occupant->update($updateData);

        $unit->update(['occupancy_type' => OccupancyType::VACANT->value]);

        // Build activity entries
        $changes = [
            ['field' => 'Occupant',   'old' => $occupant->full_name, 'new' => null],
            ['field' => 'Status',   'old' => 'Active',               'new' => 'Moved Out'],
        ];

        if (!empty($data['move_out_date'])) {
            $changes[] = ['field' => 'Move Out Date', 'old' => null, 'new' => $data['move_out_date']];
        }
        if (!empty($data['move_out_reason'])) {
            $changes[] = ['field' => 'Reason', 'old' => null, 'new' => $data['move_out_reason']];
        }
        if (!empty($data['move_out_notes'])) {
            $changes[] = ['field' => 'Notes', 'old' => null, 'new' => $data['move_out_notes']];
        }

        UnitActivity::create([
            'unit_id'         => $unit->id,
            'organization_id'       => $unit->organization_id,
            'user_id'         => $user?->id,
            'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'event'           => 'Moved out occupant',
            'category'        => 'occupant',
            'changes'         => $changes,
        ]);

        return [
            'message' => 'Occupant moved out successfully',
            'data'    => $this->showResource($occupant->fresh()),
        ];
    }

    /**
     * Reinstate an inactive occupant: set is_active to true, restore unit occupancy.
     *
     * @param Unit       $unit
     * @param Occupant $occupant
     * @return array
     * @throws Exception
     */
    public function reinstateOccupant(Unit $unit, Occupant $occupant): array
    {
        $user = Auth::user();

        if (Occupant::where('unit_id', $unit->id)->where('is_active', true)->exists()) {
            throw new Exception('This unit already has an active occupant. Move them out before reinstating another.');
        }

        $occupant->update(['is_active' => true]);

        $unit->update(['occupancy_type' => OccupancyType::OCCUPANT_OCCUPIED->value]);

        UnitActivity::create([
            'unit_id'         => $unit->id,
            'organization_id'       => $unit->organization_id,
            'user_id'         => $user?->id,
            'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'event'           => 'Reinstated occupant',
            'category'        => 'occupant',
            'changes'         => [
                ['field' => 'Occupant', 'old' => 'Inactive', 'new' => $occupant->full_name],
                ['field' => 'Status', 'old' => 'Moved Out', 'new' => 'Active'],
            ],
        ]);

        return [
            'message' => 'Occupant reinstated successfully',
            'data'    => $this->showResource($occupant->fresh()),
        ];
    }

    /**
     * Upload (or replace) the lease document for a unit occupant.
     *
     * @param Unit       $unit
     * @param Occupant $occupant
     * @param UploadedFile $file
     * @return array
     */
    public function uploadLeaseDocument(Unit $unit, Occupant $occupant, UploadedFile $file): array
    {
        // Delete the old file if one exists
        if ($occupant->lease_document_url) {
            $oldPath = str_replace(Storage::disk('public')->url(''), '', $occupant->lease_document_url);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store("lease-documents/{$occupant->organization_id}", 'public');

        $occupant->update([
            'lease_document_url'  => Storage::disk('public')->url($path),
            'lease_document_name' => $file->getClientOriginalName(),
        ]);

        return $this->showUpdatedResource($occupant->fresh());
    }

    /**
     * Delete the lease document for a unit occupant.
     *
     * @param Unit       $unit
     * @param Occupant $occupant
     * @return array
     */
    public function deleteLeaseDocument(Unit $unit, Occupant $occupant): array
    {
        if ($occupant->lease_document_url) {
            $path = str_replace(Storage::disk('public')->url(''), '', $occupant->lease_document_url);
            Storage::disk('public')->delete($path);
        }

        $occupant->update([
            'lease_document_url'  => null,
            'lease_document_name' => null,
        ]);

        return $this->showUpdatedResource($occupant->fresh());
    }

    /**
     * Bulk delete unit occupant records by an array of IDs.
     *
     * @param Unit  $unit
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteOccupants(Unit $unit, array $ids): array
    {
        $organizations = Occupant::whereIn('id', $ids)
            ->where('unit_id', $unit->id)
            ->get();

        $total = $organizations->count();

        if ($total === 0) {
            throw new Exception('No Occupants deleted');
        }

        foreach ($organizations as $occupant) {
            $occupant->delete();
        }

        $label = $total === 1 ? 'Occupant' : 'Occupants';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single unit occupant record.
     *
     * @param Unit       $unit
     * @param Occupant $occupant
     * @return array
     */
    public function deleteOccupant(Unit $unit, Occupant $occupant): array
    {
        $deleted = $occupant->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Occupant deleted' : 'Occupant delete unsuccessful',
        ];
    }
}
