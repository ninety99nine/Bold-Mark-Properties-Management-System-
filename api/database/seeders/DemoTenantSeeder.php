<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::updateOrCreate(
            ['slug' => 'boldmark'],
            [
                'name' => 'Bold Mark Properties',
                'logo_url' => '/assets/logo2-CB_yk5b_.png',
                'primary_color' => '#0B1F38',
                'accent_color' => '#D89B4B',
                'credentials' => ['NAMA-9141', 'PPRA Registered', 'Johannesburg · Botswana'],
                'copyright_name' => 'Bold Mark Properties',
                'is_active' => true,
            ]
        );
    }
}
