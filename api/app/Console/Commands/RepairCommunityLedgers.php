<?php

namespace App\Console\Commands;

use App\Models\Community;
use App\Services\CommunityLedgerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Console\Command;

class RepairCommunityLedgers extends Command
{
    protected $signature = 'communities:repair-ledgers {--org= : Limit to a specific organization ID}';

    protected $description = 'Seed missing ledgers for orgs and re-link them to all communities';

    public function handle(): void
    {
        $seeder  = new ChartOfAccountsSeeder();
        $service = new CommunityLedgerService();

        $query = Community::with('organization');

        if ($orgId = $this->option('org')) {
            $query->where('organization_id', $orgId);
        }

        $communities = $query->get();

        $this->info("Repairing ledgers for {$communities->count()} community(s)...");

        $seededOrgs = [];

        foreach ($communities as $community) {
            $orgId = $community->organization_id;

            if (!in_array($orgId, $seededOrgs)) {
                $seeder->seedForOrganization($orgId);
                $seededOrgs[] = $orgId;
                $this->line("  Seeded ledgers for org {$orgId}");
            }

            $service->setupDefaultLedgers($community);
            $this->line("  Linked ledgers for community: {$community->name}");
        }

        $this->info('Done.');
    }
}
