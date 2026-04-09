<?php

use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Unit;
use App\Models\UnitTenant;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all unit routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.units',   ['estate' => 'e']],
    ['post',   'api.v1.create.unit',  ['estate' => 'e']],
    ['get',    'api.v1.show.unit',    ['estate' => 'e', 'unit' => 'u']],
    ['put',    'api.v1.update.unit',  ['estate' => 'e', 'unit' => 'u']],
    ['delete', 'api.v1.delete.unit',  ['estate' => 'e', 'unit' => 'u']],
    ['delete', 'api.v1.delete.units', ['estate' => 'e']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated list of units for own-tenant estate', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->count(3)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(3);
});

it('returns 404 when listing units for another tenant estate', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $otherEstate))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns estate relationship on units index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_relationships=estate')
        ->assertOk();

    expect($response->json('data.0.estate'))->toHaveKey('id');
    expect($response->json('data.0.estate.id'))->toBe($estate->id);
});

it('returns owner relationship on units index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_relationships=owner')
        ->assertOk();

    expect($response->json('data.0.owner'))->toHaveKey('id');
});

it('returns chargeConfigs relationship on units index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_relationships=chargeConfigs')
        ->assertOk();

    expect($response->json('data.0.charge_configs'))->toBeArray();
});

it('returns multiple relationships on units index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_relationships=estate,owner')
        ->assertOk();

    expect($response->json('data.0.estate'))->toHaveKey('id');
    expect($response->json('data.0.owner'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units — _countable_relationships (counts)
// ──────────────────────────────────────────────────────────────────────────────

it('returns invoices_count on units index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_countable_relationships=invoices')
        ->assertOk();

    expect($response->json('data.0.invoices_count'))->toBeInt();
});

it('returns cashbook_entries_count on units index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_countable_relationships=cashbookEntries')
        ->assertOk();

    expect($response->json('data.0.cashbook_entries_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /estates/{estate}/units (create) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates an owner-occupied unit with owner details', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'Sarah van der Merwe', 'email' => 'sarah@test.com'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.unit_number', 'A01');

    $this->assertDatabaseHas('units', ['unit_number' => 'A01', 'estate_id' => $estate->id]);
});

it('creates a tenant-occupied unit with both owner and tenant details', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'B02',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'Michael Ndaba', 'email' => 'michael@test.com'],
            'tenant'         => [
                'full_name'   => 'Lisa Mokoena',
                'email'       => 'lisa@test.com',
                'rent_amount' => 9500,
                'lease_start' => '2025-03-01',
                'lease_end'   => '2026-02-28',
            ],
        ])
        ->assertCreated();

    $this->assertDatabaseHas('units', ['unit_number' => 'B02', 'occupancy_type' => 'tenant_occupied']);
    $this->assertDatabaseHas('unit_tenants', ['email' => 'lisa@test.com']);
});

it('returns 422 when unit_number is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'Test', 'email' => 'test@test.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_number']);
});

it('returns 422 when unit_number exceeds 50 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => str_repeat('x', 51),
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'Test', 'email' => 'test@test.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_number']);
});

it('returns 422 when occupancy_type is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number' => 'A01',
            'owner'       => ['full_name' => 'Test', 'email' => 'test@test.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('returns 422 when occupancy_type is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'invalid_occupancy',
            'owner'          => ['full_name' => 'Test', 'email' => 'test@test.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('accepts all valid occupancy_type values', function (string $occupancy) {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $payload = [
        'unit_number'    => 'A01',
        'occupancy_type' => $occupancy,
        'owner'          => ['full_name' => 'Test Owner', 'email' => 'test@test.com'],
    ];

    if ($occupancy === 'tenant_occupied') {
        $payload['tenant'] = ['full_name' => 'Test Tenant', 'email' => 'tenant@test.com'];
    }

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), $payload)
        ->assertCreated();
})->with(['owner_occupied', 'tenant_occupied', 'vacant']);

it('returns 422 when owner array is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'owner_occupied',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner']);
});

it('returns 422 when owner.full_name is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['email' => 'test@test.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.full_name']);
});

it('returns 422 when owner.email is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'Test Owner'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.email']);
});

it('returns 422 when owner.email is not a valid email', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'Test Owner', 'email' => 'not-an-email'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.email']);
});

it('returns 422 when tenant.full_name is missing for tenant_occupied unit', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'Owner', 'email' => 'owner@test.com'],
            'tenant'         => ['email' => 'tenant@test.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.full_name']);
});

it('returns 422 when tenant.email is missing for tenant_occupied unit', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'Owner', 'email' => 'owner@test.com'],
            'tenant'         => ['full_name' => 'Tenant'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.email']);
});

it('returns 422 when tenant.email is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'Owner', 'email' => 'owner@test.com'],
            'tenant'         => ['full_name' => 'Tenant', 'email' => 'bad-email'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.email']);
});

it('returns 422 when tenant.lease_end is before lease_start', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'Owner', 'email' => 'owner@test.com'],
            'tenant'         => [
                'full_name'   => 'Tenant',
                'email'       => 'tenant@test.com',
                'lease_start' => '2026-06-01',
                'lease_end'   => '2026-01-01',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.lease_end']);
});

it('returns 422 when levy_override is negative', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'Owner', 'email' => 'owner@test.com'],
            'levy_override'  => -100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['levy_override']);
});

it('returns 404 when creating a unit for another tenant estate', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $otherEstate), [
            'unit_number'    => 'A01',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'Owner', 'email' => 'owner@test.com'],
        ])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /estates/{estate}/units — _relationships on create response
// ──────────────────────────────────────────────────────────────────────────────

it('returns owner relationship in unit create response when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate) . '?_relationships=owner', [
            'unit_number'    => 'A01',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'Test Owner', 'email' => 'owner@test.com'],
        ])
        ->assertCreated();

    expect($response->json('data.owner'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single unit belonging to own-tenant estate', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', [$estate, $unit]))
        ->assertOk()
        ->assertJsonPath('data.id', $unit->id);
});

it('returns 404 when showing a unit from another tenant estate', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', [$otherEstate, $otherUnit]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit} — _relationships and _countable_relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns owner relationship on unit show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', [$estate, $unit]) . '?_relationships=owner')
        ->assertOk();

    expect($response->json('data.owner'))->toHaveKey('id');
});

it('returns estate relationship on unit show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', [$estate, $unit]) . '?_relationships=estate')
        ->assertOk();

    expect($response->json('data.estate'))->toHaveKey('id');
});

it('returns invoices_count on unit show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', [$estate, $unit]) . '?_countable_relationships=invoices')
        ->assertOk();

    expect($response->json('data.invoices_count'))->toBeInt();
});

it('returns cashbook_entries_count on unit show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', [$estate, $unit]) . '?_countable_relationships=cashbookEntries')
        ->assertOk();

    expect($response->json('data.cashbook_entries_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /estates/{estate}/units/{unit} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates a unit with valid data', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', [$estate, $unit]), ['unit_number' => 'A01-Updated'])
        ->assertOk()
        ->assertJsonPath('data.unit_number', 'A01-Updated');
});

it('returns 422 when update occupancy_type is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', [$estate, $unit]), ['occupancy_type' => 'bad_value'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('returns 422 when update status is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', [$estate, $unit]), ['status' => 'bad_status'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('returns 422 when update owner.email is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', [$estate, $unit]), ['owner' => ['email' => 'not-valid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.email']);
});

it('returns 422 when update tenant.lease_end is before lease_start', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', [$estate, $unit]), [
            'tenant' => ['lease_start' => '2026-12-01', 'lease_end' => '2026-01-01'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.lease_end']);
});

it('returns 404 when updating a unit from another tenant estate', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', [$otherEstate, $otherUnit]), ['unit_number' => 'Hacked'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /estates/{estate}/units/{unit} (delete single)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single unit', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit', [$estate, $unit]))
        ->assertOk();

    $this->assertDatabaseMissing('units', ['id' => $unit->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /estates/{estate}/units (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-tenant units', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $units  = Unit::factory()->count(3)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), [
            'unit_ids' => $units->pluck('id')->all(),
        ])
        ->assertOk();

    $units->each(fn ($u) => $this->assertDatabaseMissing('units', ['id' => $u->id]));
});

it('returns 422 when bulk delete unit_ids is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_ids']);
});

it('returns 422 when bulk delete unit_ids is an empty array', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), ['unit_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_ids']);
});

it('returns 422 when bulk delete unit_ids contains a non-uuid value', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), ['unit_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_ids.0']);
});
