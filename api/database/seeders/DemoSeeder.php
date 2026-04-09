<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo seeder — dev and staging only.
 * Never run in production.
 *
 * Usage: php artisan db:seed --class=DemoSeeder
 *
 * Prerequisites: php artisan db:seed (runs RolesAndPermissionsSeeder first)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoTenantSeeder::class,
            DefaultChargeTypesSeeder::class,
            DemoUsersSeeder::class,
        ]);
    }
}
