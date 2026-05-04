<?php

namespace App\Services;

use Exception;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\UnitActivity;
use App\Enums\OccupancyType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TenantResource;
use App\Http\Resources\TenantResources;

class TenantService extends BaseService
{
    /**
     * Return a paginated list of tenant history records for the given unit.
     *
     * @param Unit  $unit
     * @param array $data
     * @return TenantResources
     */
    public function showTenants(Unit $unit, array $data): TenantResources
    {
        $query = Tenant::where('unit_id', $unit->id)->with('unit');

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
    public function createTenant(Unit $unit, array $data): array
    {
        $user = Auth::user();

        // Archive any existing active tenant
        Tenant::where('unit_id', $unit->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $tenantData = collect($data)
            ->only(['full_name', 'email', 'phone', 'id_number', 'lease_start', 'lease_end', 'rent_amount'])
            ->toArray();

        $tenant = Tenant::create(array_merge($tenantData, [
            'unit_id'   => $unit->id,
            'organization_id' => $user->organization_id,
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
            !empty($tenant->full_name)   ? ['field' => 'Full Name',   'old' => null, 'new' => $tenant->full_name]   : null,
            !empty($tenant->email)        ? ['field' => 'Email',       'old' => null, 'new' => $tenant->email]        : null,
            !empty($tenant->lease_start)  ? ['field' => 'Lease Start', 'old' => null, 'new' => (string) $tenant->lease_start] : null,
            !empty($tenant->lease_end)    ? ['field' => 'Lease End',   'old' => null, 'new' => (string) $tenant->lease_end]   : null,
            !empty($tenant->rent_amount)  ? ['field' => 'Monthly Rent','old' => null, 'new' => (string) $tenant->rent_amount] : null,
        ]);

        UnitActivity::create([
            'unit_id'         => $unit->id,
            'organization_id'       => $unit->organization_id,
            'user_id'         => $user?->id,
            'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'event'           => 'Moved in tenant',
            'category'        => 'tenant',
            'changes'         => array_values($changes),
        ]);

        return $this->showCreatedResource($tenant);
    }

    /**
     * Return a single unit tenant resource.
     *
     * @param Unit       $unit
     * @param Tenant $tenant
     * @return TenantResource
     */
    public function showTenant(Unit $unit, Tenant $tenant): TenantResource
    {
        $tenant->loadMissing(['unit.estate']);
        return $this->showResource($tenant);
    }

    /**
     * Update a unit tenant's details.
     *
     * @param Unit       $unit
     * @param Tenant $tenant
     * @param array      $data
     * @return array
     */
    public function updateTenant(Unit $unit, Tenant $tenant, array $data): array
    {
        $tenantData = collect($data)
            ->only(['full_name', 'email', 'phone', 'id_number', 'lease_start', 'lease_end', 'rent_amount',
                    'move_out_date', 'move_out_reason', 'move_out_notes'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $tenant->update($tenantData);

        // Keep unit rent_amount in sync if it was updated
        if (!empty($data['rent_amount'])) {
            $unit->update(['rent_amount' => $data['rent_amount']]);
        }

        return $this->showUpdatedResource($tenant);
    }

    /**
     * Move out the current tenant: set is_active to false and update unit to vacant.
     *
     * @param Unit       $unit
     * @param Tenant $tenant
     * @return array
     */
    public function moveOutTenant(Unit $unit, Tenant $tenant, array $data = []): array
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

        $tenant->update($updateData);

        $unit->update(['occupancy_type' => OccupancyType::VACANT->value]);

        // Build activity entries
        $changes = [
            ['field' => 'Tenant',   'old' => $tenant->full_name, 'new' => null],
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
            'event'           => 'Moved out tenant',
            'category'        => 'tenant',
            'changes'         => $changes,
        ]);

        return [
            'message' => 'Tenant moved out successfully',
            'data'    => $this->showResource($tenant->fresh()),
        ];
    }

    /**
     * Reinstate an inactive tenant: set is_active to true, restore unit occupancy.
     *
     * @param Unit       $unit
     * @param Tenant $tenant
     * @return array
     * @throws Exception
     */
    public function reinstateTenant(Unit $unit, Tenant $tenant): array
    {
        $user = Auth::user();

        if (Tenant::where('unit_id', $unit->id)->where('is_active', true)->exists()) {
            throw new Exception('This unit already has an active tenant. Move them out before reinstating another.');
        }

        $tenant->update(['is_active' => true]);

        $unit->update(['occupancy_type' => OccupancyType::TENANT_OCCUPIED->value]);

        UnitActivity::create([
            'unit_id'         => $unit->id,
            'organization_id'       => $unit->organization_id,
            'user_id'         => $user?->id,
            'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'event'           => 'Reinstated tenant',
            'category'        => 'tenant',
            'changes'         => [
                ['field' => 'Tenant', 'old' => 'Inactive', 'new' => $tenant->full_name],
                ['field' => 'Status', 'old' => 'Moved Out', 'new' => 'Active'],
            ],
        ]);

        return [
            'message' => 'Tenant reinstated successfully',
            'data'    => $this->showResource($tenant->fresh()),
        ];
    }

    /**
     * Upload (or replace) the lease document for a unit tenant.
     *
     * @param Unit       $unit
     * @param Tenant $tenant
     * @param UploadedFile $file
     * @return array
     */
    public function uploadLeaseDocument(Unit $unit, Tenant $tenant, UploadedFile $file): array
    {
        // Delete the old file if one exists
        if ($tenant->lease_document_url) {
            $oldPath = str_replace(Storage::disk('public')->url(''), '', $tenant->lease_document_url);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store("lease-documents/{$tenant->organization_id}", 'public');

        $tenant->update([
            'lease_document_url'  => Storage::disk('public')->url($path),
            'lease_document_name' => $file->getClientOriginalName(),
        ]);

        return $this->showUpdatedResource($tenant->fresh());
    }

    /**
     * Delete the lease document for a unit tenant.
     *
     * @param Unit       $unit
     * @param Tenant $tenant
     * @return array
     */
    public function deleteLeaseDocument(Unit $unit, Tenant $tenant): array
    {
        if ($tenant->lease_document_url) {
            $path = str_replace(Storage::disk('public')->url(''), '', $tenant->lease_document_url);
            Storage::disk('public')->delete($path);
        }

        $tenant->update([
            'lease_document_url'  => null,
            'lease_document_name' => null,
        ]);

        return $this->showUpdatedResource($tenant->fresh());
    }

    /**
     * Bulk delete unit tenant records by an array of IDs.
     *
     * @param Unit  $unit
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteTenants(Unit $unit, array $ids): array
    {
        $organizations = Tenant::whereIn('id', $ids)
            ->where('unit_id', $unit->id)
            ->get();

        $total = $organizations->count();

        if ($total === 0) {
            throw new Exception('No Tenants deleted');
        }

        foreach ($organizations as $tenant) {
            $tenant->delete();
        }

        $label = $total === 1 ? 'Tenant' : 'Tenants';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single unit tenant record.
     *
     * @param Unit       $unit
     * @param Tenant $tenant
     * @return array
     */
    public function deleteTenant(Unit $unit, Tenant $tenant): array
    {
        $deleted = $tenant->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Tenant deleted' : 'Tenant delete unsuccessful',
        ];
    }
}
