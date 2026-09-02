<?php

use App\Models\Community;
use App\Models\SupplierGroup;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A user with a plain role (no super/company-admin) and no supplier permissions.
 * Supplier-group actions reuse the supplier.* permission strings, so we register
 * them (guard: api) to make the permission check resolve to "not granted".
 */
function supplierGroupRestrictedUser(): \App\Models\User
{
    foreach (['supplier.create', 'supplier.delete'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
    }

    return createUser(createOrganization(), 'community-manager');
}

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on every supplier-group route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.supplier.groups'],
    ['post',   'api.v1.create.supplier.group'],
    ['get',    'api.v1.show.supplier.group',   ['supplierGroup' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.supplier.group', ['supplierGroup' => '00000000-0000-0000-0000-000000000000']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/supplier-groups — index
// ──────────────────────────────────────────────────────────────────────────────

// SupplierGroupPolicy::viewAny() → Tier 1 (return true) — no 403 test needed.

it('returns the supplier group list scoped to the organization', function () {
    $user = adminUser();
    SupplierGroup::factory()->count(3)->create(['organization_id' => $user->organization_id]);
    SupplierGroup::factory()->count(2)->create(['organization_id' => createOrganization()->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.supplier.groups'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'community_id', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($resp->json('meta.total'))->toBe(3);
});

it('filters supplier groups by community (community-specific + org-shared)', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $other     = Community::factory()->create(['organization_id' => $user->organization_id]);

    SupplierGroup::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    SupplierGroup::factory()->create(['organization_id' => $user->organization_id, 'community_id' => null]);
    SupplierGroup::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $other->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.supplier.groups') . '?community_id=' . $community->id)
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /v1/supplier-groups — create
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from creating a supplier group', function () {
    $user = supplierGroupRestrictedUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier.group'), ['name' => 'Plumbers'])
        ->assertForbidden();
});

it('requires a name to create a supplier group', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier.group'), ['name' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('creates a supplier group', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier.group'), ['name' => 'Electricians'])
        ->assertOk()
        ->assertJsonPath('message', 'Created successfully')
        ->assertJsonPath('data.name', 'Electricians');

    $this->assertDatabaseHas('supplier_groups', [
        'name'            => 'Electricians',
        'organization_id' => $user->organization_id,
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/supplier-groups/{supplierGroup} — show
// ──────────────────────────────────────────────────────────────────────────────

// SupplierGroupPolicy::view() → Tier 1 (return true) — no 403 test needed.

it('returns a single supplier group', function () {
    $user  = adminUser();
    $group = SupplierGroup::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Cleaners']);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.supplier.group', $group))
        ->assertOk()
        ->assertJsonPath('data.id', $group->id)
        ->assertJsonPath('data.name', 'Cleaners');
});

it('returns 404 for a cross-organization supplier group', function () {
    $user  = adminUser();
    $group = SupplierGroup::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.supplier.group', $group))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /v1/supplier-groups/{supplierGroup} — delete
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from deleting a supplier group', function () {
    $user  = supplierGroupRestrictedUser();
    $group = SupplierGroup::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.supplier.group', $group))
        ->assertForbidden();
});

it('deletes a supplier group', function () {
    $user  = adminUser();
    $group = SupplierGroup::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.supplier.group', $group))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Supplier group deleted']);

    $this->assertDatabaseMissing('supplier_groups', ['id' => $group->id]);
});
