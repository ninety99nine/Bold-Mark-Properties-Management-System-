<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoExternalUsersSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Organization::where('slug', 'boldmark')->firstOrFail();

        $externalUsers = [
            // --- Trustees / Directors ---
            ['name' => 'Kgomotso Tlokweng',    'email' => 'k.tlokweng@gmail.com',       'phone' => '+267 71 601 1001', 'role' => 'trustee'],
            ['name' => 'Badisa Morokotso',      'email' => 'b.morokotso@hotmail.com',    'phone' => '+267 71 601 1002', 'role' => 'trustee'],
            ['name' => 'Thandiwe Nkosi',        'email' => 't.nkosi@yahoo.com',          'phone' => '+267 71 601 1003', 'role' => 'trustee'],
            ['name' => 'Omphemetse Sekgololo',  'email' => 'o.sekgololo@gmail.com',      'phone' => '+267 71 601 1004', 'role' => 'trustee'],
            ['name' => 'Kedibonye Gaefele',     'email' => 'k.gaefele@gmail.com',        'phone' => '+267 71 601 1005', 'role' => 'trustee'],

            // --- Owners (external portal access) ---
            ['name' => 'Boitumelo Molefe',      'email' => 'boi.molefe@gmail.com',       'phone' => '+267 71 602 2001', 'role' => 'owner'],
            ['name' => 'Gaositwe Sekgwele',     'email' => 'g.sekgwele@gmail.com',       'phone' => '+267 71 602 2002', 'role' => 'owner'],
            ['name' => 'Motlalepula Gaborone',  'email' => 'm.gaborone@yahoo.com',       'phone' => '+267 71 602 2003', 'role' => 'owner'],
            ['name' => 'Kelapile Ntlo',         'email' => 'k.ntlo@outlook.com',         'phone' => '+267 71 602 2004', 'role' => 'owner'],
            ['name' => 'Dimakatso Phele',       'email' => 'd.phele@gmail.com',          'phone' => '+267 71 602 2005', 'role' => 'owner'],

            // --- Tenants (external portal access) ---
            ['name' => 'Keagile Ramotshabi',    'email' => 'k.ramotshabi@gmail.com',     'phone' => '+267 71 603 3001', 'role' => 'tenant'],
            ['name' => 'Naledi Keorapetse',     'email' => 'n.keorapetse@gmail.com',     'phone' => '+267 71 603 3002', 'role' => 'tenant'],
            ['name' => 'Tumisang Gaokgakala',   'email' => 't.gaokgakala@yahoo.com',     'phone' => '+267 71 603 3003', 'role' => 'tenant'],
            ['name' => 'Emang Segolodi',        'email' => 'e.segolodi@gmail.com',       'phone' => '+267 71 603 3004', 'role' => 'tenant'],
            ['name' => 'Boikhutso Setihapi',    'email' => 'b.setihapi@hotmail.com',     'phone' => '+267 71 603 3005', 'role' => 'tenant'],

            // --- Contractors ---
            ['name' => 'Masa Plumbing Services',       'email' => 'info@masaplumbing.bw',       'phone' => '+267 31 876 4001', 'role' => 'contractor'],
            ['name' => 'Phenyo Electrical Works',      'email' => 'phenyo.elec@gmail.com',      'phone' => '+267 71 604 4002', 'role' => 'contractor'],
            ['name' => 'Kgomo Construction Ltd',       'email' => 'admin@kgomoconstruct.bw',    'phone' => '+267 31 876 4003', 'role' => 'contractor'],
            ['name' => 'TiTi Cleaning Services',       'email' => 'titi.clean@gmail.com',       'phone' => '+267 71 604 4004', 'role' => 'contractor'],
            ['name' => 'Botswana Security Solutions',  'email' => 'ops@bwsecurity.bw',          'phone' => '+267 31 876 4005', 'role' => 'contractor'],
        ];

        foreach ($externalUsers as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password'  => Hash::make('password'),
                    'organization_id' => $tenant->id,
                ])
            );

            $user->syncRoles([Role::findByName($role, 'api')]);
        }
    }
}
