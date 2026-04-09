<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create the Passport personal access client (required for API token auth).
        // Recreated here so migrate:fresh --seed always leaves the app in a working state.
        app(ClientRepository::class)->createPersonalAccessClient(
            null,
            config('app.name') . ' Personal Access Client',
            config('app.url')
        );

        // System seeds — always run in every environment
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Demo seeds — automatically included in local and staging environments.
        // Never runs in production. Safe to call via: php artisan migrate:fresh --seed
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
