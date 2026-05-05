<?php

namespace App\Console\Commands;

use App\Models\Estate;
use App\Services\EstateChargeTypeService;
use Database\Seeders\DefaultChargeTypesSeeder;
use Illuminate\Console\Command;

class RepairEstateChargeTypes extends Command
{
    protected $signature = 'estates:repair-charge-types {--org= : Limit to a specific organization ID}';

    protected $description = 'Seed missing charge types for orgs and re-link them to all estates';

    public function handle(): void
    {
        $seeder  = new DefaultChargeTypesSeeder();
        $service = new EstateChargeTypeService();

        $query = Estate::with('organization');

        if ($orgId = $this->option('org')) {
            $query->where('organization_id', $orgId);
        }

        $estates = $query->get();

        $this->info("Repairing charge types for {$estates->count()} estate(s)...");

        $seededOrgs = [];

        foreach ($estates as $estate) {
            $orgId = $estate->organization_id;

            if (!in_array($orgId, $seededOrgs)) {
                $seeder->seedForTenant($orgId);
                $seededOrgs[] = $orgId;
                $this->line("  Seeded charge types for org {$orgId}");
            }

            $service->setupDefaultChargeTypes($estate);
            $this->line("  Linked charge types for estate: {$estate->name}");
        }

        $this->info('Done.');
    }
}
