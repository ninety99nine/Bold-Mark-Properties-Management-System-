<?php

namespace App\Services;

use App\Models\Organization;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\TenantResources;

class OrganizationService extends BaseService
{
    /**
     * Return a single tenant resource with its estates loaded.
     *
     * @param Organization $tenant
     * @return OrganizationResource
     */
    public function showTenant(Organization $tenant): OrganizationResource
    {
        $tenant->load(['estates']);

        return $this->showResource($tenant);
    }

    /**
     * Update the tenant's company settings and branding.
     *
     * @param Organization $tenant
     * @param array  $data
     * @return array
     */
    public function updateTenant(Organization $tenant, array $data): array
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
