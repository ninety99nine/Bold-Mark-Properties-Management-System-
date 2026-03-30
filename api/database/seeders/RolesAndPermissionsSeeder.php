<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- Permissions ---
        $permissions = [
            // Financials
            'view-financials',
            'manage-financials',

            // Levies
            'view-levies',
            'manage-levies',
            'approve-levies',

            // Debt
            'view-debt',
            'manage-debt',
            'approve-debt-actions',

            // Compliance
            'view-compliance',
            'manage-compliance',

            // Maintenance
            'view-maintenance',
            'manage-maintenance',
            'assign-contractors',

            // Users & Communities
            'manage-users',
            'manage-communities',
            'manage-tenants',

            // Reports
            'view-reports',
            'export-reports',

            // Communications
            'send-communications',

            // Documents
            'manage-documents',

            // Payments
            'approve-payments',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // --- Roles ---

        // Super Admin — full system access (Optimum Quality)
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        $superAdmin->givePermissionTo(Permission::all());

        // Company Admin — managing agent company administrator
        $companyAdmin = Role::firstOrCreate(['name' => 'company-admin', 'guard_name' => 'api']);
        $companyAdmin->givePermissionTo([
            'view-financials', 'manage-financials',
            'view-levies', 'manage-levies', 'approve-levies',
            'view-debt', 'manage-debt', 'approve-debt-actions',
            'view-compliance', 'manage-compliance',
            'view-maintenance', 'manage-maintenance', 'assign-contractors',
            'manage-users', 'manage-communities',
            'view-reports', 'export-reports',
            'send-communications',
            'manage-documents',
        ]);

        // Portfolio Manager
        $portfolioManager = Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'api']);
        $portfolioManager->givePermissionTo([
            'view-financials',
            'view-levies', 'manage-levies',
            'view-debt', 'manage-debt',
            'view-compliance', 'manage-compliance',
            'view-maintenance', 'manage-maintenance', 'assign-contractors',
            'view-reports', 'export-reports',
            'send-communications',
            'manage-documents',
        ]);

        // Financial Controller
        $financialController = Role::firstOrCreate(['name' => 'financial-controller', 'guard_name' => 'api']);
        $financialController->givePermissionTo([
            'view-financials', 'manage-financials',
            'view-levies', 'manage-levies',
            'view-debt', 'manage-debt',
            'view-reports', 'export-reports',
        ]);

        // Portfolio Assistant
        $portfolioAssistant = Role::firstOrCreate(['name' => 'portfolio-assistant', 'guard_name' => 'api']);
        $portfolioAssistant->givePermissionTo([
            'view-financials',
            'view-levies',
            'view-debt',
            'view-compliance',
            'view-maintenance', 'manage-maintenance',
            'send-communications',
            'manage-documents',
        ]);

        // Trustee — community director/trustee (external)
        $trustee = Role::firstOrCreate(['name' => 'trustee', 'guard_name' => 'api']);
        $trustee->givePermissionTo([
            'view-financials',
            'view-reports',
            'approve-payments',
        ]);

        // Owner — unit owner (external)
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']);
        $owner->givePermissionTo([
            'view-financials',
        ]);
    }
}
