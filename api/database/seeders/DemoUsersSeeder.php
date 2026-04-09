<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'boldmark')->firstOrFail();

        $users = [
            [
                'name'      => 'Justin Sobhee',
                'email'     => 'admin@demo.boldmark.test',
                'password'  => Hash::make('password'),
                'phone'     => '+27 82 555 0001',
                'tenant_id' => $tenant->id,
                'role'      => 'company-admin',
            ],
            [
                'name'      => 'Thabo Ndlovu',
                'email'     => 'pm@demo.boldmark.test',
                'password'  => Hash::make('password'),
                'phone'     => '+27 82 555 0002',
                'tenant_id' => $tenant->id,
                'role'      => 'portfolio-manager',
            ],
            [
                'name'      => 'Lerato Pillay',
                'email'     => 'fc@demo.boldmark.test',
                'password'  => Hash::make('password'),
                'phone'     => '+27 82 555 0003',
                'tenant_id' => $tenant->id,
                'role'      => 'financial-controller',
            ],
            [
                'name'      => 'Naledi Khumalo',
                'email'     => 'pa@demo.boldmark.test',
                'password'  => Hash::make('password'),
                'phone'     => '+27 82 555 0004',
                'tenant_id' => $tenant->id,
                'role'      => 'portfolio-assistant',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $user->syncRoles([\Spatie\Permission\Models\Role::findByName($role, 'api')]);
        }
    }
}
