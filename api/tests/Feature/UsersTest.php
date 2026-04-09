<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all user routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.users'],
    ['get',    'api.v1.show.users.summary'],
    ['post',   'api.v1.invite.user'],
    ['delete', 'api.v1.delete.users'],
    ['get',    'api.v1.show.user',   ['user' => 99999]],
    ['put',    'api.v1.update.user', ['user' => 99999]],
    ['delete', 'api.v1.delete.user', ['user' => 99999]],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /users (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated list of users scoped to user tenant', function () {
    $actor = adminUser();

    // Create 3 more users in same tenant
    User::factory()->count(3)->create(['tenant_id' => $actor->tenant_id]);

    // Users from another tenant — must NOT appear
    User::factory()->count(2)->create(['tenant_id' => createTenant()->id]);

    $response = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.users'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    // 1 actor + 3 created = 4 total
    expect($response->json('meta.total'))->toBe(4);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /users — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns tenant relationship on users index when requested', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.users') . '?_relationships=tenant')
        ->assertOk();

    expect($response->json('data.0.tenant'))->toHaveKey('id');
});

it('returns roles relationship on users index when requested', function () {
    $user = adminUser();
    $role = Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);
    $user->assignRole($role);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.users') . '?_relationships=roles')
        ->assertOk();

    expect($response->json('data.0.roles'))->toBeArray();
});

it('returns tenant and roles relationships together on users index', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.users') . '?_relationships=tenant,roles')
        ->assertOk();

    expect($response->json('data.0.tenant'))->toHaveKey('id');
    expect($response->json('data.0.roles'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /users/summary
// ──────────────────────────────────────────────────────────────────────────────

it('returns user summary statistics', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.users.summary'))
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /users (invite user) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('invites a new user with valid data', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Thabo Ndlovu',
            'email' => 'thabo@boldmark.co.za',
            'phone' => '+267 71234567',
            'role'  => 'portfolio-manager',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Thabo Ndlovu');

    $this->assertDatabaseHas('users', [
        'email'     => 'thabo@boldmark.co.za',
        'tenant_id' => $actor->tenant_id,
    ]);
});

it('returns 422 when invite name is missing', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'email' => 'noname@example.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when invite name exceeds 255 characters', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => str_repeat('x', 256),
            'email' => 'longname@example.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when invite email is missing', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name' => 'No Email',
            'role' => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when invite email is not a valid email address', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Bad Email',
            'email' => 'not-a-valid-email',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when invite email exceeds 255 characters', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Long Email',
            'email' => str_repeat('a', 244) . '@example.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when invite email is already in use', function () {
    $actor    = adminUser();
    $existing = User::factory()->create(['tenant_id' => $actor->tenant_id]);
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Duplicate',
            'email' => $existing->email,
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when invite phone exceeds 30 characters', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Long Phone',
            'email' => 'longphone@example.com',
            'phone' => str_repeat('1', 31),
            'role'  => 'portfolio-manager',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('returns 422 when invite role is missing', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'No Role',
            'email' => 'norole@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('returns 422 when invite role does not exist', function () {
    $actor = adminUser();

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'Bad Role',
            'email' => 'badrole@example.com',
            'role'  => 'non_existent_role',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('allows phone to be omitted on invite', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user'), [
            'name'  => 'No Phone',
            'email' => 'nophone@example.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertCreated();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /users — _relationships on invite response
// ──────────────────────────────────────────────────────────────────────────────

it('returns tenant relationship in invite response when requested', function () {
    $actor = adminUser();
    Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);

    $response = $this->actingAs($actor, 'api')
        ->postJson(route('api.v1.invite.user') . '?_relationships=tenant', [
            'name'  => 'Rel Test',
            'email' => 'reltest@example.com',
            'role'  => 'portfolio-manager',
        ])
        ->assertCreated();

    expect($response->json('data.tenant'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /users/{user} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single user from same tenant', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['tenant_id' => $actor->tenant_id]);

    $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.user', $target))
        ->assertOk()
        ->assertJsonPath('data.id', $target->id);
});

it('returns 404 when viewing a user from another tenant', function () {
    $actor     = adminUser();
    $otherUser = User::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.user', $otherUser))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /users/{user} — _relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns tenant relationship on user show when requested', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['tenant_id' => $actor->tenant_id]);

    $response = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.user', $target) . '?_relationships=tenant')
        ->assertOk();

    expect($response->json('data.tenant'))->toHaveKey('id');
});

it('returns roles relationship on user show when requested', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['tenant_id' => $actor->tenant_id]);
    $role   = Role::firstOrCreate(['name' => 'portfolio-manager', 'guard_name' => 'web']);
    $target->assignRole($role);

    $response = $this->actingAs($actor, 'api')
        ->getJson(route('api.v1.show.user', $target) . '?_relationships=roles')
        ->assertOk();

    expect($response->json('data.roles'))->toBeArray();
    expect(count($response->json('data.roles')))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /users/{user} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates a user name and phone', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['tenant_id' => $actor->tenant_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), [
            'name'  => 'Updated Name',
            'phone' => '+267 79999999',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

it('allows partial user update — only provided fields changed', function () {
    $actor        = adminUser();
    $target       = User::factory()->create(['tenant_id' => $actor->tenant_id]);
    $originalName = $target->name;

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['phone' => '+267 79123456'])
        ->assertOk();

    $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => $originalName]);
});

it('returns 422 when update email is not a valid email address', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['tenant_id' => $actor->tenant_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), [
            'email' => 'not-a-valid-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when update name exceeds 255 characters', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['tenant_id' => $actor->tenant_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['name' => str_repeat('x', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when update phone exceeds 30 characters', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['tenant_id' => $actor->tenant_id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $target), ['phone' => str_repeat('1', 31)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('returns 404 when updating a user from another tenant', function () {
    $actor     = adminUser();
    $otherUser = User::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($actor, 'api')
        ->putJson(route('api.v1.update.user', $otherUser), ['name' => 'Hacked'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /users/{user} (delete single)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a user from same tenant', function () {
    $actor  = adminUser();
    $target = User::factory()->create(['tenant_id' => $actor->tenant_id]);

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.user', $target))
        ->assertOk();

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

it('returns 404 when deleting a user from another tenant', function () {
    $actor     = adminUser();
    $otherUser = User::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.user', $otherUser))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /users (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes users from same tenant', function () {
    $actor   = adminUser();
    $targets = User::factory()->count(3)->create(['tenant_id' => $actor->tenant_id]);

    $this->actingAs($actor, 'api')
        ->deleteJson(route('api.v1.delete.users'), [
            'user_ids' => $targets->pluck('id')->all(),
        ])
        ->assertOk();

    $targets->each(fn ($u) => $this->assertDatabaseMissing('users', ['id' => $u->id]));
});

it('returns 422 when bulk delete user_ids is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.users'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids']);
});

it('returns 422 when bulk delete user_ids is an empty array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids']);
});

it('returns 422 when bulk delete user_ids contains a non-integer value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.users'), ['user_ids' => ['not-an-integer']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids.0']);
});
