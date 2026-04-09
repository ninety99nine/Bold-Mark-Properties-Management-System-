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
                'name'            => 'Bold Mark Properties',
                'company_name'    => 'Bold Mark Properties',
                'company_slogan'  => 'Moving People Forward',
                'logo_url'        => '/assets/logo2-CB_yk5b_.png',
                'contact_email'   => 'info@boldmarkprop.co.za',
                'contact_phone'   => '+27 10 442 0012',
                'address'         => '112 Boeing Rd, Bedfordview, Johannesburg',
                'country'         => 'ZA',
                'currency'        => 'ZAR',
                'primary_color'   => '#0B1F38',
                'secondary_color' => '#D89B4B',
                'credentials'     => ['NAMA-9141', 'PPRA Registered', 'Johannesburg · Botswana'],
                'copyright_name'  => 'Bold Mark Properties',
                'is_active'       => true,
            ]
        );
    }
}
