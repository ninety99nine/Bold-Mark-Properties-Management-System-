<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Unit;
use App\Services\UnitBalanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrator — seeds estates for all portfolio regions.
 *
 * Delegates to country-specific seeders that share a common
 * infrastructure base class (DemoEstatesBaseSeeder).
 */
class DemoEstatesSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Organization::where('slug', 'boldmark')->firstOrFail();

        // Wipe previously seeded uploaded files so every run is clean
        Storage::disk('public')->deleteDirectory("proof_of_payment/{$tenant->id}");
        Storage::disk('public')->deleteDirectory('tenants');

        // Seed both portfolio regions in order
        $this->call(DemoBotswanaEstatesSeeder::class);
        $this->call(DemoSouthAfricaEstatesSeeder::class);

        // Recalculate unit balances after all invoices and payments are seeded
        $this->command?->info('Recalculating unit balances...');
        $balanceService = app(UnitBalanceService::class);
        Unit::where('organization_id', $tenant->id)->each(function (Unit $unit) use ($balanceService) {
            $balanceService->recalculate($unit);
        });
    }
}
