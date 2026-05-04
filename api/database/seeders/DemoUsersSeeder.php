<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Organization::where('slug', 'boldmark')->firstOrFail();

        $users = [
            [
                'name'      => 'Justin Justin',
                'email'     => 'justin@boldmarkprop.co.za',
                'password'  => Hash::make('password'),
                'phone'     => '+267 72 555 0001',
                'organization_id' => $tenant->id,
                'role'      => 'company-admin',
            ],
            [
                'name'      => 'Julian Tabona',
                'email'     => 'brandontabona@gmail.com',
                'password'  => Hash::make('password'),
                'phone'     => '+27 82 555 0000',
                'organization_id' => $tenant->id,
                'role'      => 'company-admin',
            ],
            [
                'name'      => 'Thabo Ndlovu',
                'email'     => 'pm@demo.boldmark.test',
                'password'  => Hash::make('password'),
                'phone'     => '+267 72 555 0002',
                'organization_id' => $tenant->id,
                'role'      => 'portfolio-manager',
            ],
            [
                'name'      => 'Lerato Pillay',
                'email'     => 'fc@demo.boldmark.test',
                'password'  => Hash::make('password'),
                'phone'     => '+267 72 555 0003',
                'organization_id' => $tenant->id,
                'role'      => 'financial-controller',
            ],
            [
                'name'      => 'Naledi Khumalo',
                'email'     => 'pa@demo.boldmark.test',
                'password'  => Hash::make('password'),
                'phone'     => '+267 72 555 0004',
                'organization_id' => $tenant->id,
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

            $user->syncRoles([Role::findByName($role, 'api')]);
        }
    }
}
