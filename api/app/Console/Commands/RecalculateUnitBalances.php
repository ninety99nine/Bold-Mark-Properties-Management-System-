<?php

namespace App\Console\Commands;

use App\Services\UnitBalanceService;
use Illuminate\Console\Command;

class RecalculateUnitBalances extends Command
{
    protected $signature = 'units:recalculate-balances
                            {--organization= : Only recalculate units for this organization ID}';

    protected $description = 'Recalculate and persist the stored balance column for all units';

    public function handle(UnitBalanceService $service): int
    {
        $organizationId = $this->option('organization') ?: null;

        $this->info($organizationId
            ? "Recalculating balances for organization {$organizationId}…"
            : 'Recalculating balances for all units…'
        );

        $count = $service->recalculateAll($organizationId);

        $this->info("Done. {$count} " . ($count === 1 ? 'unit' : 'units') . ' updated.');

        return self::SUCCESS;
    }
}
