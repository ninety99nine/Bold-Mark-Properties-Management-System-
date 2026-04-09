<?php

use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all owner routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.owners'],
    ['get',    'api.v1.show.owner',   ['owner' => 'non-existent']],
    ['put',    'api.v1.update.owner', ['owner' => 'non-existent']],
    ['delete', 'api.v1.delete.owner', ['owner' => 'non-existent']],
    ['delete', 'api.v1.delete.owners'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /owners (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated list of owners scoped to tenant', function () {
    $user        = adminUser();
    $estate      = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->count(3)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    // Owners from another tenant — must NOT appear
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    Unit::factory()->count(2)->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /owners — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on owners index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
});

it('returns invoices relationship on owners index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_relationships=invoices')
        ->assertOk();

    expect($response->json('data.0.invoices'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /owners — _countable_relationships (counts)
// ──────────────────────────────────────────────────────────────────────────────

it('returns invoices_count on owners index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_countable_relationships=invoices')
        ->assertOk();

    expect($response->json('data.0.invoices_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /owners/{owner} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single owner belonging to the user tenant', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $owner))
        ->assertOk()
        ->assertJsonPath('data.id', $owner->id);
});

it('returns 404 when showing an owner from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherOwner  = Owner::where('unit_id', $otherUnit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $otherOwner))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /owners/{owner} — _relationships and _countable_relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on owner show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $owner) . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.unit'))->toHaveKey('id');
});

it('returns invoices relationship on owner show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $owner) . '?_relationships=invoices')
        ->assertOk();

    expect($response->json('data.invoices'))->toBeArray();
});

it('returns invoices_count on owner show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $owner) . '?_countable_relationships=invoices')
        ->assertOk();

    expect($response->json('data.invoices_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /owners/{owner} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates owner details with valid data', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), [
            'full_name' => 'Updated Owner Name',
            'phone'     => '+27821234567',
        ])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Updated Owner Name');

    $this->assertDatabaseHas('owners', ['id' => $owner->id, 'full_name' => 'Updated Owner Name']);
});

it('allows partial owner update — only provided fields changed', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();
    $originalName = $owner->full_name;

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['phone' => '+27831234567'])
        ->assertOk();

    $this->assertDatabaseHas('owners', ['id' => $owner->id, 'full_name' => $originalName]);
});

it('returns 422 when update owner email is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['email' => 'not-a-valid-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when update owner full_name exceeds 255 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['full_name' => str_repeat('x', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('returns 422 when update owner address exceeds 500 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['address' => str_repeat('x', 501)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address']);
});

it('returns 422 when update owner phone exceeds 30 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['phone' => str_repeat('1', 31)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('returns 404 when updating an owner from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherOwner  = Owner::where('unit_id', $otherUnit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $otherOwner), ['full_name' => 'Hacked'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /owners/{owner} (delete single)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single owner', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owner', $owner))
        ->assertOk();

    $this->assertDatabaseMissing('owners', ['id' => $owner->id]);
});

it('returns 404 when deleting an owner from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherOwner  = Owner::where('unit_id', $otherUnit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owner', $otherOwner))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /owners (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-tenant owners', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $units  = Unit::factory()->count(3)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $ownerIds = Owner::whereIn('unit_id', $units->pluck('id'))->pluck('id')->all();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => $ownerIds])
        ->assertOk();
});

it('returns 422 when bulk delete owner_ids is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_ids']);
});

it('returns 422 when bulk delete owner_ids is an empty array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_ids']);
});

it('returns 422 when bulk delete owner_ids contains a non-uuid value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_ids.0']);
});
