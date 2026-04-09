<?php

namespace App\Console\Commands;

use App\Services\UnitBalanceService;
use Illuminate\Console\Command;

class RecalculateUnitBalances extends Command
{
    protected $signature = 'units:recalculate-balances
                            {--tenant= : Only recalculate units for this tenant ID}';

    protected $description = 'Recalculate and persist the stored balance column for all units';

    public function handle(UnitBalanceService $service): int
    {
        $tenantId = $this->option('tenant') ?: null;

        $this->info($tenantId
            ? "Recalculating balances for tenant {$tenantId}…"
            : 'Recalculating balances for all units…'
        );

        $count = $service->recalculateAll($tenantId);

        $this->info("Done. {$count} " . ($count === 1 ? 'unit' : 'units') . ' updated.');

        return self::SUCCESS;
    }
}
