<?php

use App\Enums\UserStatus;
use App\Models\Estate;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Spatie resolves roles via the active auth guard. Tests run users through
 * `auth:api`, so we create roles with `guard_name='api'` to keep
 * `assignRole()` and `hasRole()` happy on both seed-side and runtime checks.
 * (The Pest.php createUser helper uses `'web'` for legacy reasons —
 * we override here so invite/assign flows resolve cleanly.)
 */
function ensureRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
}

function makeUserInTenant(string $tenantId, array $overrides = []): User
{
    return User::factory()->create(array_merge(['organization_id' => $tenantId], $overrides));
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unauthenticated access                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on every user route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.users'],
    ['get',    'api.v1.show.users.summary'],
    ['post',   'api.v1.invite.user'],
    ['delete', 'api.v1.delete.users'],
    ['get',    'api.v1.show.user',           ['user' => 99999]],
    ['put',    'api.v1.update.user',         ['user' => 99999]],
    ['delete', 'api.v1.delete.user',         ['user' => 99999]],
    ['post',   'api.v1.send.password.reset', ['user' => 99999]],
    ['post',   'api.v1.reset.two.factor',    ['user' => 99999]],
    ['put',    'api.v1.sync.user.estates',   ['user' => 99999]],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/users  —  index                                                  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns the paginator structure', function () {
    $actor = adminUser();
    User::factory()->count(3)->create(['organization_id' => $actor->organization_id]);

    $resp = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users'))
        ->assertOk()
        ->assertJsonStructure([
            'data'  => [['id', 'name', 'email', 'phone', 'status', 'organization_id', 'created_at', 'updated_at',
                         'email_verified', 'roles', 'estates']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta'  => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ]);

    // 1 actor + 3 created = 4 total
    expect($resp->json('meta.total'))->toBe(4);
    expect($resp->json('meta.per_page'))->toBe(15);
});

it('only returns users belonging to the authenticated user\'s tenant', function () {
    $actor = adminUser();
    User::factory()->count(3)->create(['organization_id' => $actor->organization_id]);
    User::factory()->count(2)->create(['organization_id' => createTenant()->id]);

    $resp = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users'))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(4);
    foreach ($resp->json('data') as $row) {
        expect($row['organization_id'])->toBe($actor->organization_id);
    }
});

it('does not expose password or remember_token in the list', function () {
    $actor = adminUser();
    User::factory()->create(['organization_id' => $actor->organization_id]);

    $row = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users'))
        ->assertOk()
        ->json('data.0');

    expect($row)->not->toHaveKey('password');
    expect($row)->not->toHaveKey('remember_token');
});

it('returns email_verified=true when email_verified_at is set', function () {
    $actor = adminUser();
    User::factory()->create(['organization_id' => $actor->organization_id, 'email_verified_at' => now()]);

    $rows = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_per_page=50')
        ->assertOk()
        ->json('data.*.email_verified');

    expect($rows)->each->toBeTrue();
});

it('returns email_verified=false for unverified users', function () {
    $actor      = adminUser();
    $unverified = User::factory()->unverified()->create(['organization_id' => $actor->organization_id]);

    $rows = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users'))
        ->assertOk()
        ->json('data');

    $row = collect($rows)->firstWhere('id', $unverified->id);
    expect($row['email_verified'])->toBeFalse();
});

it('orders users by latest created_at by default', function () {
    $actor = adminUser();
    $oldest = User::factory()->create(['organization_id' => $actor->organization_id, 'created_at' => now()->subDays(3)]);
    $newest = User::factory()->create(['organization_id' => $actor->organization_id, 'created_at' => now()->addMinute()]);

    $first = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users'))
        ->assertOk()
        ->json('data.0.id');

    expect($first)->toBe($newest->id);
});

it('respects _per_page', function () {
    $actor = adminUser();
    User::factory()->count(7)->create(['organization_id' => $actor->organization_id]);

    $resp = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_per_page=3')
        ->assertOk();

    expect($resp->json('meta.per_page'))->toBe(3);
    expect(count($resp->json('data')))->toBe(3);
});

it('paginates correctly across pages', function () {
    $actor = adminUser();
    User::factory()->count(5)->create(['organization_id' => $actor->organization_id]);

    $page2 = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_per_page=2&page=2')
        ->assertOk();

    expect($page2->json('meta.current_page'))->toBe(2);
    expect(count($page2->json('data')))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters — role, status
// ──────────────────────────────────────────────────────────────────────────────

it('filters by role', function () {
    $actor          = adminUser();
    $managerRole    = ensureRole('portfolio-manager');
    $assistantRole  = ensureRole('portfolio-assistant');

    $manager = User::factory()->create(['organization_id' => $actor->organization_id]);
    $manager->assignRole($managerRole);
    $assistant = User::factory()->create(['organization_id' => $actor->organization_id]);
    $assistant->assignRole($assistantRole);

    $resp = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?role=portfolio-manager')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
    expect($resp->json('data.0.id'))->toBe($manager->id);
});

it('filters by status', function (string $status) {
    $actor = adminUser();
    foreach (UserStatus::values() as $s) {
        User::factory()->create(['organization_id' => $actor->organization_id, 'status' => $s]);
    }

    $resp = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . "?status={$status}")
        ->assertOk();

    foreach ($resp->json('data') as $row) {
        expect($row['status'])->toBe($status);
    }
})->with(['active', 'invited', 'inactive']);

// ──────────────────────────────────────────────────────────────────────────────
// Sort
// ──────────────────────────────────────────────────────────────────────────────

it('sorts users by name asc', function () {
    $actor = adminUser();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $n) {
        User::factory()->create(['organization_id' => $actor->organization_id, 'name' => $n]);
    }

    $names = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_sort=name:asc')
        ->assertOk()
        ->json('data.*.name');

    // The actor mixes into the result with their own faker-generated name —
    // verify the three named users are in alphabetical order relative to each other.
    $a = array_search('Alpha',   $names);
    $b = array_search('Bravo',   $names);
    $c = array_search('Charlie', $names);
    expect($a)->toBeLessThan($b);
    expect($b)->toBeLessThan($c);
});

it('sorts users by name desc', function () {
    $actor = adminUser();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $n) {
        User::factory()->create(['organization_id' => $actor->organization_id, 'name' => $n]);
    }

    $names = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_sort=name:desc')
        ->assertOk()
        ->json('data.*.name');

    $a = array_search('Alpha',   $names);
    $b = array_search('Bravo',   $names);
    $c = array_search('Charlie', $names);
    expect($c)->toBeLessThan($b);
    expect($b)->toBeLessThan($a);
});

it('sanitises malicious _sort column input — table is not dropped', function () {
    $actor = adminUser();
    User::factory()->count(2)->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_sort=' . urlencode("name'); DROP TABLE users; --:asc"));

    expect(\Illuminate\Support\Facades\DB::table('users')->count())->toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range
// ──────────────────────────────────────────────────────────────────────────────

it('filters by _date_range=today', function () {
    $actor = adminUser();
    User::factory()->create(['organization_id' => $actor->organization_id, 'created_at' => now()]);
    User::factory()->create(['organization_id' => $actor->organization_id, 'created_at' => now()->subDays(2)]);

    $resp = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_date_range=today')
        ->assertOk();

    // The actor was also created today — so 2 records total.
    expect($resp->json('meta.total'))->toBe(2);
});

it('filters by _date_range=custom with start + end', function () {
    $actor = adminUser();
    User::factory()->create(['organization_id' => $actor->organization_id, 'created_at' => Carbon::parse('2024-02-15')]);
    User::factory()->create(['organization_id' => $actor->organization_id, 'created_at' => Carbon::parse('2024-04-15')]);
    User::factory()->create(['organization_id' => $actor->organization_id, 'created_at' => Carbon::parse('2024-06-15')]);

    $resp = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_date_range=custom&_date_range_start=2024-03-01&_date_range_end=2024-05-31')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (Postgres-only)
// ──────────────────────────────────────────────────────────────────────────────

it('searches users by name / email (Postgres ilike)', function () {
    $actor = adminUser();
    User::factory()->create(['organization_id' => $actor->organization_id, 'name' => 'Crystal Ndlovu']);

    $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users') . '?_search=Crystal')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/users/summary                                                    ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns the expected summary keys', function () {
    $actor = adminUser();

    $body = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users.summary'))
        ->assertOk()
        ->json();

    expect($body)->toHaveKeys(['total', 'active', 'invited', 'inactive', 'internal_count', 'external_count']);
});

it('aggregates by status across own-tenant users only', function () {
    $actor = adminUser();
    User::factory()->create(['organization_id' => $actor->organization_id, 'status' => 'active']);
    User::factory()->count(2)->create(['organization_id' => $actor->organization_id, 'status' => 'invited']);
    User::factory()->create(['organization_id' => $actor->organization_id, 'status' => 'inactive']);
    // Foreign-tenant user — must NOT count.
    User::factory()->create(['organization_id' => createTenant()->id, 'status' => 'active']);

    $body = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users.summary'))
        ->assertOk()
        ->json();

    // Actor (active) + 1 active + 2 invited + 1 inactive = 5 total
    expect($body['total'])->toBe(5);
    expect($body['active'])->toBe(2);
    expect($body['invited'])->toBe(2);
    expect($body['inactive'])->toBe(1);
});

it('counts internal vs external users by role', function () {
    $actor = adminUser(); // company-admin = internal
    $internalRole  = ensureRole('portfolio-manager');
    $externalRole  = ensureRole('owner');

    $internal = User::factory()->create(['organization_id' => $actor->organization_id]);
    $internal->assignRole($internalRole);
    $external = User::factory()->create(['organization_id' => $actor->organization_id]);
    $external->assignRole($externalRole);
    User::factory()->create(['organization_id' => $actor->organization_id]); // no roles

    $body = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users.summary'))
        ->assertOk()
        ->json();

    // Actor (company-admin) + portfolio-manager = 2 internal
    expect($body['internal_count'])->toBe(2);
    // 1 external-role + 1 no-role = 2 external
    expect($body['external_count'])->toBe(2);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/users  —  invite                                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// Validation rules

it('rejects invite without name', function () {
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'email' => 'x@x.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects invite with name > 255 chars', function () {
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => str_repeat('z', 256),
            'email' => 'x@x.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects invite with name not a string', function () {
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => ['nested'],
            'email' => 'x@x.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects invite without email', function () {
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), ['name' => 'X', 'role' => 'portfolio-manager'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects invite with invalid email format', function (string $bad) {
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'X',
            'email' => $bad,
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
})->with(['notanemail', '@x.com', 'has spaces@x.com']);

it('rejects invite with email > 255 chars', function () {
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'X',
            'email' => str_repeat('a', 250) . '@example.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects invite when email is already in use', function () {
    $actor    = adminUser();
    $existing = User::factory()->create(['organization_id' => $actor->organization_id, 'email' => 'taken@x.com']);
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Dup',
            'email' => 'taken@x.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('email-uniqueness check is global (not per-tenant) — invite fails when another tenant uses the email', function () {
    $actor = adminUser();
    User::factory()->create(['organization_id' => createTenant()->id, 'email' => 'shared@x.com']);
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'X',
            'email' => 'shared@x.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects invite with phone > 30 chars', function () {
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'X',
            'email' => 'x@x.com',
            'phone' => str_repeat('1', 31),
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('rejects invite without role', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), ['name' => 'X', 'email' => 'x@x.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('rejects invite with non-existent role', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'X',
            'email' => 'x@x.com',
            'role'  => 'imaginary-role',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

// Successful invite & side effects

it('invites a new user — creates record with INVITED status, assigns role, sends invitation email', function () {
    Notification::fake();
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $resp = $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Thabo Ndlovu',
            'email' => 'thabo@boldmark.test',
            'phone' => '+267 71234567',
            'role'  => 'portfolio-manager',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Created successfully')
        ->assertJsonPath('data.name', 'Thabo Ndlovu')
        ->assertJsonPath('data.email', 'thabo@boldmark.test')
        ->assertJsonPath('data.status', 'invited')
        ->assertJsonPath('data.organization_id', $actor->organization_id);

    $newUserId = $resp->json('data.id');

    $this->assertDatabaseHas('users', [
        'id'        => $newUserId,
        'email'     => 'thabo@boldmark.test',
        'organization_id' => $actor->organization_id,
        'status'    => 'invited',
    ]);

    // Password is randomly generated + hashed (NOT plaintext, NOT empty).
    $newUser = User::find($newUserId);
    expect($newUser->password)->toBeString()->toStartWith('$2y$');

    // Role assigned (Spatie pivot).
    expect($newUser->hasRole('portfolio-manager'))->toBeTrue();
});

it('persists an invitation token in password_reset_tokens for the new user', function () {
    Notification::fake();
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Invitee',
            'email' => 'invitee@x.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertOk();

    $row = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
        ->where('email', 'invitee@x.com')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->token)->toBeString()->not->toBeEmpty();
});

it('forces organization_id from auth — clients cannot spoof it on invite', function () {
    Notification::fake();
    $actor = adminUser();
    $other = createTenant();
    ensureRole('portfolio-manager');

    $resp = $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'      => 'Spoofer',
            'email'     => 'spoofer@x.com',
            'role'      => 'portfolio-manager',
            'organization_id' => $other->id,
        ])
        ->assertOk();

    expect($resp->json('data.organization_id'))->toBe($actor->organization_id);
});

it('does not return password or remember_token on invite response', function () {
    Notification::fake();
    $actor = adminUser();
    ensureRole('portfolio-manager');

    $row = $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Hidden',
            'email' => 'hidden@x.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertOk()
        ->json('data');

    expect($row)->not->toHaveKey('password');
    expect($row)->not->toHaveKey('remember_token');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/users/{user}  —  show                                            ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns a single user with roles and estates eager-loaded', function () {
    $actor = adminUser();
    $role  = ensureRole('portfolio-manager');
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);
    $target->assignRole($role);

    $estate = Estate::factory()->create(['organization_id' => $actor->organization_id]);
    $target->estates()->attach($estate);

    $body = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.user', $target))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'status', 'organization_id',
                       'roles' => [['id', 'name']],
                       'estates' => [['id', 'name']]],
        ])
        ->json();

    expect($body['data']['id'])->toBe($target->id);
    expect(collect($body['data']['roles'])->pluck('name')->all())->toContain('portfolio-manager');
    expect(collect($body['data']['estates'])->pluck('id')->all())->toContain($estate->id);
});

it('returns 404 for an unknown user id', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.user', ['user' => 999999]))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/users/{user}  —  update                                          ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('rejects update with name > 255 chars', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['name' => str_repeat('z', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects update with invalid email', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects update with phone > 30 chars', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['phone' => str_repeat('1', 31)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('rejects update with non-existent role', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['role' => 'imaginary'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('rejects update with invalid status', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['status' => 'cosmic'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('updates a user with a partial payload — returns updated resource + message', function () {
    $actor  = adminUser();
    $target = User::factory()->create([
        'organization_id' => $actor->organization_id,
        'name'      => 'Old Name',
        'email'     => 'old@x.com',
        'phone'     => '+27 11 0000',
    ]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', 'old@x.com')
        ->assertJsonPath('data.phone', '+27 11 0000');
});

it('replaces the user role via syncRoles when role is sent', function () {
    $actor   = adminUser();
    $oldRole = ensureRole('portfolio-assistant');
    $newRole = ensureRole('financial-controller');
    $target  = User::factory()->create(['organization_id' => $actor->organization_id]);
    $target->assignRole($oldRole);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['role' => 'financial-controller'])
        ->assertOk();

    $fresh = $target->fresh();
    expect($fresh->hasRole('financial-controller'))->toBeTrue();
    expect($fresh->hasRole('portfolio-assistant'))->toBeFalse(); // syncRoles wipes the previous role
});

it('updates status — accepts every valid UserStatus value', function (string $status) {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id, 'status' => 'active']);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['status' => $status])
        ->assertOk()
        ->assertJsonPath('data.status', $status);
})->with(['active', 'invited', 'inactive']);

it('allows a user to update themselves even without admin permission', function () {
    $tenant = createTenant();
    $self   = createUser($tenant, 'portfolio-manager');

    $this->actingAs($self, 'api')
        ->putJson(route('api.v1.update.user', $self), ['name' => 'Renamed Self'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Self');
});

it('forbids a non-admin from updating another user', function () {
    $tenant = createTenant();
    $self   = createUser($tenant, 'portfolio-manager');
    $other  = createUser($tenant, 'portfolio-manager');

    $this->actingAs($self, 'api')
        ->putJson(route('api.v1.update.user', $other), ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($other->fresh()->name)->not->toBe('Hijacked');
});

it('returns 404 when updating an unknown user id', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', ['user' => 999999]), ['name' => 'X'])
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/users/{user}  —  single                                       ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('deletes a single user', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.user', $target))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'User deleted']);

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

it('forbids a user from deleting themselves via the single-delete route', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.user', $actor))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $actor->id]);
});

it('returns 404 when deleting an unknown user id', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.user', ['user' => 999999]))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/users  —  bulk                                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('bulk deletes own-tenant users and pluralises the message', function () {
    $actor = adminUser();
    $users = User::factory()->count(3)->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => $users->pluck('id')->all()])
        ->assertOk()
        ->assertJson(['message' => '3 Users deleted']);

    foreach ($users as $u) {
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }
});

it('uses the singular label when bulk-deleting one', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => [$target->id]])
        ->assertOk()
        ->assertJson(['message' => '1 User deleted']);
});

it('silently filters the actor out of bulk delete to prevent self-deletion', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => [$actor->id, $target->id]])
        ->assertOk()
        ->assertJson(['message' => '1 User deleted']);

    $this->assertDatabaseHas('users',     ['id' => $actor->id]);
    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

it('only deletes own-tenant users when a mix of own + cross-tenant ids is supplied', function () {
    $actor    = adminUser();
    $own      = User::factory()->create(['organization_id' => $actor->organization_id]);
    $foreign  = User::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => [$own->id, $foreign->id]])
        ->assertOk()
        ->assertJson(['message' => '1 User deleted']);

    $this->assertDatabaseMissing('users', ['id' => $own->id]);
    $this->assertDatabaseHas('users',     ['id' => $foreign->id]);
});

it('returns 500 when the actor attempts to delete only themselves (after self-filter, no users left)', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => [$actor->id]])
        ->assertStatus(500); // service throws "No Users deleted"

    $this->assertDatabaseHas('users', ['id' => $actor->id]);
});

it('rejects bulk delete with non-integer user_ids', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => ['not-an-integer']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids.0']);
});

it('rejects bulk delete with non-existent user ids', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => [999999, 999998]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids.0']);
});

it('rejects bulk delete when user_ids is not an array', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids']);
});

it('returns 403 when bulk delete user_ids is missing or empty (policy guard)', function (array $payload) {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), $payload)
        ->assertForbidden();
})->with([
    'missing' => [[]],
    'empty'   => [['user_ids' => []]],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/users/{user}/send-password-reset                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('sends a password reset link to the target user', function () {
    Notification::fake();
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id, 'email' => 'reset@x.com']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.send.password.reset', $target))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Password reset link sent to reset@x.com',
        ]);

    Notification::assertSentTo($target, ResetPassword::class);
});

it('returns success=false with a friendly message when the broker rejects (e.g. throttled)', function () {
    Notification::fake();
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id, 'email' => 'throttle@x.com']);

    // First call succeeds.
    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.send.password.reset', $target))
        ->assertOk()
        ->assertJsonPath('success', true);

    // Second call within throttle window — broker returns RESET_THROTTLED, controller returns success=false.
    $body = $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.send.password.reset', $target))
        ->assertOk()
        ->json();

    expect($body['success'])->toBeFalse();
    expect($body['message'])->toContain('Failed to send password reset link');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/users/{user}/estates  —  sync user estates                       ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('attaches estates to a user via sync', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);
    $a      = Estate::factory()->create(['organization_id' => $actor->organization_id]);
    $b      = Estate::factory()->create(['organization_id' => $actor->organization_id]);

    $resp = $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.sync.user.estates', $target), ['estate_ids' => [$a->id, $b->id]])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully');

    $estateIds = collect($resp->json('data.estates'))->pluck('id')->sort()->values()->all();
    expect($estateIds)->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('replaces the existing estate set on subsequent sync calls', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);
    $a      = Estate::factory()->create(['organization_id' => $actor->organization_id]);
    $b      = Estate::factory()->create(['organization_id' => $actor->organization_id]);
    $c      = Estate::factory()->create(['organization_id' => $actor->organization_id]);

    // First: attach a + b.
    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.sync.user.estates', $target), ['estate_ids' => [$a->id, $b->id]])
        ->assertOk();

    // Then: sync to c only — a + b must be detached.
    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.sync.user.estates', $target), ['estate_ids' => [$c->id]])
        ->assertOk();

    $ids = $target->fresh()->estates()->pluck('estates.id')->sort()->values()->all();
    expect($ids)->toBe([$c->id]);
});

it('clears every estate assignment when estate_ids is an empty array', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);
    $estate = Estate::factory()->create(['organization_id' => $actor->organization_id]);
    $target->estates()->attach($estate);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.sync.user.estates', $target), ['estate_ids' => []])
        ->assertOk();

    expect($target->fresh()->estates()->count())->toBe(0);
});

it('rejects sync-estates when estate_ids is missing entirely (present rule)', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.sync.user.estates', $target), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_ids']);
});

it('rejects sync-estates with a non-uuid id', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.sync.user.estates', $target), ['estate_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_ids.0']);
});

it('rejects sync-estates with an unknown (non-existent) estate id', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['organization_id' => $actor->organization_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.sync.user.estates', $target), ['estate_ids' => ['00000000-0000-0000-0000-000000000000']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_ids.0']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Cross-tenant isolation                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('cross-tenant user show returns 404', function () {
    $actor   = adminUser();
    $foreign = User::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.user', $foreign))
        ->assertNotFound();
});

it('cross-tenant user update returns 404', function () {
    $actor   = adminUser();
    $foreign = User::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $foreign), ['name' => 'Hijacked'])
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/users/{user}/reset-2fa  —  admin 2FA recovery (BUG-003)         ║
// ╚══════════════════════════════════════════════════════════════════════════╝

/** Enrol confirmed 2FA on a user so the reset has something to clear. */
function enrol2fa(User $user): void
{
    $user->forceFill([
        'two_factor_secret'       => \Illuminate\Support\Facades\Crypt::encryptString('TESTSECRET234567'),
        'two_factor_confirmed_at' => now(),
    ])->save();
}

it('lets a company admin reset another user\'s 2FA, forcing re-enrolment', function () {
    $actor  = adminUser();
    $target = makeUserInTenant($actor->organization_id);
    enrol2fa($target);

    expect($target->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.reset.two.factor', $target))
        ->assertOk()
        ->assertJsonStructure(['message']);

    $fresh = $target->fresh();
    expect($fresh->hasTwoFactorEnabled())->toBeFalse();
    expect($fresh->two_factor_secret)->toBeNull();
    expect($fresh->two_factor_confirmed_at)->toBeNull();
});

it('revokes the target\'s sessions when their 2FA is reset', function () {
    $actor  = adminUser();
    $target = makeUserInTenant($actor->organization_id);
    enrol2fa($target);

    \App\Models\UserSession::create([
        'user_id'          => $target->id,
        'token_id'         => 'tok-reset-1',
        'ip_address'       => '127.0.0.1',
        'user_agent'       => 'phpunit',
        'last_activity_at' => now(),
        'remember'         => false,
        'created_at'       => now(),
    ]);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.reset.two.factor', $target))
        ->assertOk();

    expect(\App\Models\UserSession::where('user_id', $target->id)->count())->toBe(0);
});

it('forbids a non-admin from resetting another user\'s 2FA', function () {
    $tenant = createTenant();
    $actor  = createUser($tenant, 'portfolio-manager');
    $target = makeUserInTenant($tenant->id);
    enrol2fa($target);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.reset.two.factor', $target))
        ->assertForbidden();

    expect($target->fresh()->hasTwoFactorEnabled())->toBeTrue();
});
