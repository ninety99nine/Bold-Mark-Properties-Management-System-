<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $org = Organization::firstOrCreate(
            ['slug' => 'boldmark'],
            [
                'name'          => 'BoldMark Properties',
                'company_name'  => 'Bold Mark Properties',
                'contact_email' => 'info@boldmarkprop.co.za',
                'country'       => 'ZA',
                'currency'      => 'ZAR',
                'is_active'     => true,
            ]
        );

        $password = env('BOLDMARK_ADMIN_PASSWORD', 'BoldMark@2026!');

        $users = [
            ['name' => 'Justin Justin',   'email' => 'justin@boldmarkprop.co.za'],
            ['name' => 'Ayanda Habana',   'email' => 'ayanda@boldmarkprop.co.za'],
            ['name' => 'Julian Tabona',   'email' => 'julian@boldmarkprop.co.za'],
        ];

        $role = Role::findByName('company-admin', 'api');

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'            => $data['name'],
                    'password'        => Hash::make($password),
                    'organization_id' => $org->id,
                ]
            );

            $user->syncRoles([$role]);
        }

        $this->command->info('BoldMark org + 3 users created. Temp password: ' . $password);
    }
}
