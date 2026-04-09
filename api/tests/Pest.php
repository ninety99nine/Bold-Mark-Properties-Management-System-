<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a fresh Tenant record.
 */
function createTenant(): \App\Models\Tenant
{
    return \App\Models\Tenant::factory()->create();
}

/**
 * Create a User belonging to the given tenant, assigned the given Spatie role.
 */
function createUser(\App\Models\Tenant $tenant, string $role = 'company-admin'): \App\Models\User
{
    $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);

    $roleModel = \Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => $role, 'guard_name' => 'web']
    );

    $user->assignRole($roleModel);

    return $user;
}

/**
 * Shorthand: new tenant + company-admin user.
 */
function adminUser(): \App\Models\User
{
    return createUser(createTenant(), 'company-admin');
}

/**
 * Shorthand: new tenant + super-admin user.
 */
function superAdminUser(): \App\Models\User
{
    return createUser(createTenant(), 'super-admin');
}

/**
 * Create a company-admin user belonging to a *different* tenant.
 * Used for multi-tenancy isolation tests.
 */
function otherTenantUser(): \App\Models\User
{
    return createUser(createTenant(), 'company-admin');
}
