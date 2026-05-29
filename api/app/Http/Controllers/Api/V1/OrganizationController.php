<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\FlushOrganizationJob;
use App\Models\FlushJob;
use App\Services\OrganizationService;
use App\Http\Resources\OrganizationResource;
use App\Http\Requests\Organization\FlushOrganizationRequest;
use App\Http\Requests\Organization\ShowOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    protected OrganizationService $service;

    public function __construct(OrganizationService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the authenticated user's tenant (company settings).
     */
    public function showCurrentTenant(Request $request): OrganizationResource
    {
        $tenant = $request->user()->organization;

        return $this->service->showTenant($tenant);
    }

    /**
     * Update the authenticated user's tenant company settings and branding.
     */
    public function updateCurrentTenant(UpdateOrganizationRequest $request): array
    {
        $tenant = $request->user()->organization;

        return $this->service->updateTenant($tenant, $request->validated());
    }

    /**
     * Dispatch a background job to flush selected data categories.
     * Returns a job ID for progress polling.
     */
    public function flushCurrentTenant(FlushOrganizationRequest $request): array
    {
        $tenant = $request->user()->organization;

        $this->authorize('flush', $tenant);

        $validated = $request->validated();

        // Strip display-only targets that are handled internally (compliance_checklists via estates)
        $targets = array_values(array_filter(
            $validated['targets'],
            fn($t) => $t !== 'compliance_checklists'
        ));

        $steps = FlushOrganizationJob::buildSteps($targets);

        $flushJob = FlushJob::create([
            'organization_id' => $tenant->id,
            'status'          => 'dispatched',
            'steps'           => $steps,
        ]);

        FlushOrganizationJob::dispatch(
            $flushJob->id,
            $tenant->id,
            $targets,
            $validated['keep_user_ids'] ?? [],
        );

        return [
            'job_id'  => $flushJob->id,
            'message' => 'Flush job dispatched.',
        ];
    }

    /**
     * Return the current progress of a flush job.
     */
    public function flushStatus(Request $request, string $jobId): array
    {
        $flushJob = FlushJob::where('id', $jobId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        return [
            'status' => $flushJob->status,
            'steps'  => $flushJob->steps,
            'error'  => $flushJob->error,
        ];
    }
}
