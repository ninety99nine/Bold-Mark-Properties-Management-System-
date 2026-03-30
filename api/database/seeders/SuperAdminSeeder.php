<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'super@optimumquality.co.za'],
            [
                'name' => 'Optimum Quality Admin',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'changeme-in-production')),
            ]
        );

        $superAdmin->assignRole(\Spatie\Permission\Models\Role::findByName('super-admin', 'api'));
    }
}
