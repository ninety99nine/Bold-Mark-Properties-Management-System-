<?php

use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Unit;
use App\Models\UnitTenant;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all unit tenant routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.unit.tenants',       ['estate' => 'e', 'unit' => 'u']],
    ['post',   'api.v1.create.unit.tenant',      ['estate' => 'e', 'unit' => 'u']],
    ['get',    'api.v1.show.unit.tenant',         ['estate' => 'e', 'unit' => 'u', 'unitTenant' => 't']],
    ['put',    'api.v1.update.unit.tenant',       ['estate' => 'e', 'unit' => 'u', 'unitTenant' => 't']],
    ['post',   'api.v1.move.out.unit.tenant',     ['estate' => 'e', 'unit' => 'u', 'unitTenant' => 't']],
    ['delete', 'api.v1.delete.unit.tenant',       ['estate' => 'e', 'unit' => 'u', 'unitTenant' => 't']],
    ['delete', 'api.v1.delete.unit.tenants',      ['estate' => 'e', 'unit' => 'u']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit}/tenants (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns tenant history including inactive tenants', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    UnitTenant::factory()->count(2)->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id, 'is_active' => false]);
    UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id, 'is_active' => true]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenants', [$estate, $unit]))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(3);
});

it('returns 404 when listing tenants for another tenant unit', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenants', [$otherEstate, $otherUnit]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit}/tenants — _relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on unit tenants index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id, 'is_active' => true]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenants', [$estate, $unit]) . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
});

it('returns invoices relationship on unit tenants index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id, 'is_active' => true]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenants', [$estate, $unit]) . '?_relationships=invoices')
        ->assertOk();

    expect($response->json('data.0.invoices'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit}/tenants — _countable_relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns invoices_count on unit tenants index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id, 'is_active' => true]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenants', [$estate, $unit]) . '?_countable_relationships=invoices')
        ->assertOk();

    expect($response->json('data.0.invoices_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /estates/{estate}/units/{unit}/tenants (create / move-in) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates a new tenant for a unit (move-in)', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'full_name'   => 'Lisa Mokoena',
            'email'       => 'lisa@test.com',
            'phone'       => '+26771234567',
            'rent_amount' => 9500,
            'lease_start' => '2026-01-01',
            'lease_end'   => '2026-12-31',
        ])
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Lisa Mokoena');

    $this->assertDatabaseHas('unit_tenants', ['email' => 'lisa@test.com', 'unit_id' => $unit->id]);
});

it('returns 422 when tenant full_name is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'email' => 'lisa@test.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('returns 422 when tenant full_name exceeds 255 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'full_name' => str_repeat('x', 256),
            'email'     => 'lisa@test.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('returns 422 when tenant email is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'full_name' => 'Lisa Mokoena',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when tenant email is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'full_name' => 'Lisa Mokoena',
            'email'     => 'not-a-valid-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when tenant email exceeds 255 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'full_name' => 'Lisa Mokoena',
            'email'     => str_repeat('a', 246) . '@test.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when tenant lease_end is before lease_start', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'full_name'   => 'Lisa Mokoena',
            'email'       => 'lisa@test.com',
            'lease_start' => '2026-06-01',
            'lease_end'   => '2026-01-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_end']);
});

it('returns 422 when tenant rent_amount is negative', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'full_name'   => 'Lisa Mokoena',
            'email'       => 'lisa@test.com',
            'rent_amount' => -500,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rent_amount']);
});

it('allows optional tenant fields to be omitted', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.tenant', [$estate, $unit]), [
            'full_name' => 'Lisa Mokoena',
            'email'     => 'lisa@test.com',
        ])
        ->assertCreated();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit}/tenants/{unitTenant} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single unit tenant', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenant', [$estate, $unit, $unitTenant]))
        ->assertOk()
        ->assertJsonPath('data.id', $unitTenant->id);
});

it('returns 404 when showing a tenant from another tenant unit', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->tenantOccupied()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherUnitTenant = UnitTenant::factory()->create(['unit_id' => $otherUnit->id, 'tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenant', [$otherEstate, $otherUnit, $otherUnitTenant]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit}/tenants/{unitTenant} — _relationships and _countable
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on unit tenant show when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenant', [$estate, $unit, $unitTenant]) . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.unit'))->toHaveKey('id');
});

it('returns invoices_count on unit tenant show when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.tenant', [$estate, $unit, $unitTenant]) . '?_countable_relationships=invoices')
        ->assertOk();

    expect($response->json('data.invoices_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /estates/{estate}/units/{unit}/tenants/{unitTenant} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates a unit tenant with valid data', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.tenant', [$estate, $unit, $unitTenant]), [
            'full_name'   => 'Updated Tenant Name',
            'rent_amount' => 10000,
        ])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Updated Tenant Name');
});

it('returns 422 when update tenant email is invalid', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.tenant', [$estate, $unit, $unitTenant]), [
            'email' => 'not-valid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when update tenant lease_end is before lease_start', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.tenant', [$estate, $unit, $unitTenant]), [
            'lease_start' => '2026-12-01',
            'lease_end'   => '2026-01-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_end']);
});

it('returns 422 when update tenant rent_amount is negative', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.tenant', [$estate, $unit, $unitTenant]), [
            'rent_amount' => -100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rent_amount']);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /estates/{estate}/units/{unit}/tenants/{unitTenant}/move-out
// ──────────────────────────────────────────────────────────────────────────────

it('moves out an active tenant', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.unit.tenant', [$estate, $unit, $unitTenant]))
        ->assertOk();

    $this->assertDatabaseHas('unit_tenants', ['id' => $unitTenant->id, 'is_active' => false]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /estates/{estate}/units/{unit}/tenants/{unitTenant} (delete)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single unit tenant record', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $unitTenant = UnitTenant::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.tenant', [$estate, $unit, $unitTenant]))
        ->assertOk();

    $this->assertDatabaseMissing('unit_tenants', ['id' => $unitTenant->id]);
});
