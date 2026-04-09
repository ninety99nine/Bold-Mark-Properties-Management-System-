<?php

namespace App\Services;

use Exception;
use App\Models\Unit;
use App\Models\UnitTenant;
use App\Models\UnitActivity;
use App\Enums\OccupancyType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\UnitTenantResource;
use App\Http\Resources\UnitTenantResources;

class UnitTenantService extends BaseService
{
    /**
     * Return a paginated list of tenant history records for the given unit.
     *
     * @param Unit  $unit
     * @param array $data
     * @return UnitTenantResources
     */
    public function showUnitTenants(Unit $unit, array $data): UnitTenantResources
    {
        $query = UnitTenant::where('unit_id', $unit->id)->with('unit');

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
     * Move in a new tenant: deactivate any existing active tenant, create the new record,
     * and set the unit occupancy to tenant_occupied.
     *
     * @param Unit  $unit
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createUnitTenant(Unit $unit, array $data): array
    {
        $user = Auth::user();

        // Archive any existing active tenant
        UnitTenant::where('unit_id', $unit->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $tenantData = collect($data)
            ->only(['full_name', 'email', 'phone', 'id_number', 'lease_start', 'lease_end', 'rent_amount'])
            ->toArray();

        $unitTenant = UnitTenant::create(array_merge($tenantData, [
            'unit_id'   => $unit->id,
            'tenant_id' => $user->tenant_id,
            'is_active' => true,
        ]));

        // Update unit occupancy type and rent amount if provided
        $unitUpdate = ['occupancy_type' => OccupancyType::TENANT_OCCUPIED->value];

        if (!empty($data['rent_amount'])) {
            $unitUpdate['rent_amount'] = $data['rent_amount'];
        }

        $unit->update($unitUpdate);

        // Log move-in event
        $changes = array_filter([
            !empty($unitTenant->full_name)   ? ['field' => 'Full Name',   'old' => null, 'new' => $unitTenant->full_name]   : null,
            !empty($unitTenant->email)        ? ['field' => 'Email',       'old' => null, 'new' => $unitTenant->email]        : null,
            !empty($unitTenant->lease_start)  ? ['field' => 'Lease Start', 'old' => null, 'new' => (string) $unitTenant->lease_start] : null,
            !empty($unitTenant->lease_end)    ? ['field' => 'Lease End',   'old' => null, 'new' => (string) $unitTenant->lease_end]   : null,
            !empty($unitTenant->rent_amount)  ? ['field' => 'Monthly Rent','old' => null, 'new' => (string) $unitTenant->rent_amount] : null,
        ]);

        UnitActivity::create([
            'unit_id'         => $unit->id,
            'tenant_id'       => $unit->tenant_id,
            'user_id'         => $user?->id,
            'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'event'           => 'Moved in tenant',
            'category'        => 'tenant',
            'changes'         => array_values($changes),
        ]);

        return $this->showCreatedResource($unitTenant);
    }

    /**
     * Return a single unit tenant resource.
     *
     * @param Unit       $unit
     * @param UnitTenant $unitTenant
     * @return UnitTenantResource
     */
    public function showUnitTenant(Unit $unit, UnitTenant $unitTenant): UnitTenantResource
    {
        $unitTenant->loadMissing(['unit.estate']);
        return $this->showResource($unitTenant);
    }

    /**
     * Update a unit tenant's details.
     *
     * @param Unit       $unit
     * @param UnitTenant $unitTenant
     * @param array      $data
     * @return array
     */
    public function updateUnitTenant(Unit $unit, UnitTenant $unitTenant, array $data): array
    {
        $tenantData = collect($data)
            ->only(['full_name', 'email', 'phone', 'id_number', 'lease_start', 'lease_end', 'rent_amount'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $unitTenant->update($tenantData);

        // Keep unit rent_amount in sync if it was updated
        if (!empty($data['rent_amount'])) {
            $unit->update(['rent_amount' => $data['rent_amount']]);
        }

        return $this->showUpdatedResource($unitTenant);
    }

    /**
     * Move out the current tenant: set is_active to false and update unit to vacant.
     *
     * @param Unit       $unit
     * @param UnitTenant $unitTenant
     * @return array
     */
    public function moveOutUnitTenant(Unit $unit, UnitTenant $unitTenant, array $data = []): array
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

        $unitTenant->update($updateData);

        $unit->update(['occupancy_type' => OccupancyType::VACANT->value]);

        // Build activity entries
        $changes = [
            ['field' => 'Tenant',   'old' => $unitTenant->full_name, 'new' => null],
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
            'tenant_id'       => $unit->tenant_id,
            'user_id'         => $user?->id,
            'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'event'           => 'Moved out tenant',
            'category'        => 'tenant',
            'changes'         => $changes,
        ]);

        return [
            'message' => 'Tenant moved out successfully',
            'data'    => $this->showResource($unitTenant->fresh()),
        ];
    }

    /**
     * Reinstate an inactive tenant: set is_active to true, restore unit occupancy.
     *
     * @param Unit       $unit
     * @param UnitTenant $unitTenant
     * @return array
     * @throws Exception
     */
    public function reinstateUnitTenant(Unit $unit, UnitTenant $unitTenant): array
    {
        $user = Auth::user();

        if (UnitTenant::where('unit_id', $unit->id)->where('is_active', true)->exists()) {
            throw new Exception('This unit already has an active tenant. Move them out before reinstating another.');
        }

        $unitTenant->update(['is_active' => true]);

        $unit->update(['occupancy_type' => OccupancyType::TENANT_OCCUPIED->value]);

        UnitActivity::create([
            'unit_id'         => $unit->id,
            'tenant_id'       => $unit->tenant_id,
            'user_id'         => $user?->id,
            'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'event'           => 'Reinstated tenant',
            'category'        => 'tenant',
            'changes'         => [
                ['field' => 'Tenant', 'old' => 'Inactive', 'new' => $unitTenant->full_name],
                ['field' => 'Status', 'old' => 'Moved Out', 'new' => 'Active'],
            ],
        ]);

        return [
            'message' => 'Tenant reinstated successfully',
            'data'    => $this->showResource($unitTenant->fresh()),
        ];
    }

    /**
     * Upload (or replace) the lease document for a unit tenant.
     *
     * @param Unit       $unit
     * @param UnitTenant $unitTenant
     * @param UploadedFile $file
     * @return array
     */
    public function uploadLeaseDocument(Unit $unit, UnitTenant $unitTenant, UploadedFile $file): array
    {
        // Delete the old file if one exists
        if ($unitTenant->lease_document_url) {
            $oldPath = str_replace(Storage::disk('public')->url(''), '', $unitTenant->lease_document_url);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store("lease-documents/{$unitTenant->tenant_id}", 'public');

        $unitTenant->update([
            'lease_document_url'  => Storage::disk('public')->url($path),
            'lease_document_name' => $file->getClientOriginalName(),
        ]);

        return $this->showUpdatedResource($unitTenant->fresh());
    }

    /**
     * Delete the lease document for a unit tenant.
     *
     * @param Unit       $unit
     * @param UnitTenant $unitTenant
     * @return array
     */
    public function deleteLeaseDocument(Unit $unit, UnitTenant $unitTenant): array
    {
        if ($unitTenant->lease_document_url) {
            $path = str_replace(Storage::disk('public')->url(''), '', $unitTenant->lease_document_url);
            Storage::disk('public')->delete($path);
        }

        $unitTenant->update([
            'lease_document_url'  => null,
            'lease_document_name' => null,
        ]);

        return $this->showUpdatedResource($unitTenant->fresh());
    }

    /**
     * Bulk delete unit tenant records by an array of IDs.
     *
     * @param Unit  $unit
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteUnitTenants(Unit $unit, array $ids): array
    {
        $tenants = UnitTenant::whereIn('id', $ids)
            ->where('unit_id', $unit->id)
            ->get();

        $total = $tenants->count();

        if ($total === 0) {
            throw new Exception('No Tenants deleted');
        }

        foreach ($tenants as $tenant) {
            $tenant->delete();
        }

        $label = $total === 1 ? 'Tenant' : 'Tenants';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single unit tenant record.
     *
     * @param Unit       $unit
     * @param UnitTenant $unitTenant
     * @return array
     */
    public function deleteUnitTenant(Unit $unit, UnitTenant $unitTenant): array
    {
        $deleted = $unitTenant->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Tenant deleted' : 'Tenant delete unsuccessful',
        ];
    }
}
