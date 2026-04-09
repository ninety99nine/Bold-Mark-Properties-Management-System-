<?php

namespace App\Services;

use App\Models\Tenant;
use App\Http\Resources\TenantResource;
use App\Http\Resources\TenantResources;

class TenantService extends BaseService
{
    /**
     * Return a single tenant resource with its estates loaded.
     *
     * @param Tenant $tenant
     * @return TenantResource
     */
    public function showTenant(Tenant $tenant): TenantResource
    {
        $tenant->load(['estates']);

        return $this->showResource($tenant);
    }

    /**
     * Update the tenant's company settings and branding.
     *
     * @param Tenant $tenant
     * @param array  $data
     * @return array
     */
    public function updateTenant(Tenant $tenant, array $data): array
    {
        $updateData = collect($data)
            ->only([
                'company_name',
                'company_slogan',
                'contact_email',
                'contact_phone',
                'address',
                'country',
                'currency',
                'primary_color',
                'secondary_color',
                'copyright_name',
            ])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $tenant->update($updateData);

        return $this->showUpdatedResource($tenant);
    }
}
