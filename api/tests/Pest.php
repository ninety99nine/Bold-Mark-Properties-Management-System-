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
| SQLite — register Postgres-equivalent SQL functions & operators
|--------------------------------------------------------------------------
|
| Production runs on Postgres which ships GREATEST / LEAST natively and
| supports the ilike case-insensitive operator. Our test suite uses SQLite
| (in-memory) which has none of these. Register UDFs for GREATEST / LEAST
| and swap the query grammar to rewrite `ilike` → `like` (SQLite LIKE is
| already case-insensitive for ASCII text, so behaviour is identical).
*/
uses()->beforeEach(function () {
    static $registeredOnPdoIds = [];

    $connection = \Illuminate\Support\Facades\DB::connection();

    if ($connection->getDriverName() !== 'sqlite') {
        return;
    }

    $pdo   = $connection->getPdo();
    $pdoId = spl_object_id($pdo);

    if (isset($registeredOnPdoIds[$pdoId])) {
        return;
    }

    $pdo->sqliteCreateFunction('GREATEST', function (...$args) {
        $args = array_filter($args, fn ($v) => $v !== null);
        return empty($args) ? null : max($args);
    });
    $pdo->sqliteCreateFunction('LEAST', function (...$args) {
        $args = array_filter($args, fn ($v) => $v !== null);
        return empty($args) ? null : min($args);
    });

    // Rewrite ilike → like at the grammar level so Postgres-style case-insensitive
    // searches work transparently in the SQLite test environment.
    $connection->setQueryGrammar(
        new class($connection) extends \Illuminate\Database\Query\Grammars\SQLiteGrammar {
            public function __construct(\Illuminate\Database\Connection $conn)
            {
                parent::__construct($conn);
            }

            protected function whereBasic(\Illuminate\Database\Query\Builder $query, $where)
            {
                if (strtolower($where['operator']) === 'ilike') {
                    $where['operator'] = 'like';
                }
                return parent::whereBasic($query, $where);
            }
        }
    );

    $registeredOnPdoIds[$pdoId] = true;
})->in('Feature');

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
 * Create a fresh Organization record.
 */
function createTenant(): \App\Models\Organization
{
    return \App\Models\Organization::factory()->create();
}

/**
 * Create a User belonging to the given tenant, assigned the given Spatie role.
 */
function createUser(\App\Models\Organization $tenant, string $role = 'company-admin'): \App\Models\User
{
    $user = \App\Models\User::factory()->create(['organization_id' => $tenant->id]);

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
