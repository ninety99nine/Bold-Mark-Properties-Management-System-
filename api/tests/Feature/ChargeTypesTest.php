<?php

use App\Models\ChargeType;
use App\Models\Estate;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all charge type routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.charge.types'],
    ['post',   'api.v1.create.charge.type'],
    ['get',    'api.v1.show.charge.type',   ['chargeType' => 'non-existent']],
    ['put',    'api.v1.update.charge.type', ['chargeType' => 'non-existent']],
    ['delete', 'api.v1.delete.charge.type', ['chargeType' => 'non-existent']],
    ['delete', 'api.v1.delete.charge.types'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /charge-types (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated list of charge types scoped to tenant', function () {
    $user = adminUser();
    ChargeType::factory()->count(3)->create(['tenant_id' => $user->tenant_id]);
    ChargeType::factory()->count(2)->create(['tenant_id' => createTenant()->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.charge.types'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /charge-types — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns estates relationship on charge types index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $estate->chargeTypes()->attach($chargeType->id);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.charge.types') . '?_relationships=estates')
        ->assertOk();

    expect($response->json('data.0.estates'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /charge-types/{chargeType} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single charge type belonging to the user tenant', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.charge.type', $chargeType))
        ->assertOk()
        ->assertJsonPath('data.id', $chargeType->id);
});

it('returns 404 when showing a charge type from another tenant', function () {
    $user            = adminUser();
    $otherChargeType = ChargeType::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.charge.type', $otherChargeType))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /charge-types/{chargeType} — _relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns estates relationship on charge type show when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $estate->chargeTypes()->attach($chargeType->id);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.charge.type', $chargeType) . '?_relationships=estates')
        ->assertOk();

    expect($response->json('data.estates'))->toBeArray();
    expect(count($response->json('data.estates')))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /charge-types (create) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates a charge type with valid data', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'GENERATOR_FEE',
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'GENERATOR_FEE');

    $this->assertDatabaseHas('charge_types', [
        'code'      => 'GENERATOR_FEE',
        'tenant_id' => $user->tenant_id,
    ]);
});

it('returns 422 when charge type code is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('returns 422 when charge type code contains lowercase letters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'generator_fee',
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('returns 422 when charge type code contains spaces', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'GENERATOR FEE',
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('returns 422 when charge type code exceeds 50 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => str_repeat('A', 51),
            'name'         => 'Test',
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('returns 422 when charge type name is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'GENERATOR_FEE',
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when charge type name exceeds 255 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'GEN_FEE',
            'name'         => str_repeat('x', 256),
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when applies_to is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'GENERATOR_FEE',
            'name'         => 'Generator Fee',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['applies_to']);
});

it('returns 422 when applies_to is invalid', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'GENERATOR_FEE',
            'name'         => 'Generator Fee',
            'applies_to'   => 'everyone',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['applies_to']);
});

it('accepts all valid applies_to values', function (string $appliesTo) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'FEE_' . strtoupper($appliesTo),
            'name'         => 'Fee for ' . $appliesTo,
            'applies_to'   => $appliesTo,
            'is_recurring' => false,
        ])
        ->assertCreated();
})->with(['owner', 'tenant', 'either']);

it('returns 422 when is_recurring is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'       => 'GENERATOR_FEE',
            'name'       => 'Generator Fee',
            'applies_to' => 'either',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_recurring']);
});

it('returns 422 when sort_order is less than 1', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'GEN_FEE',
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => true,
            'sort_order'   => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_order']);
});

it('allows nullable sort_order to be omitted', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'code'         => 'GEN_FEE',
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => false,
        ])
        ->assertCreated();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /charge-types — _relationships on create response
// ──────────────────────────────────────────────────────────────────────────────

it('returns estates relationship in charge type create response when requested', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type') . '?_relationships=estates', [
            'code'         => 'GEN_FEE',
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => false,
        ])
        ->assertCreated();

    expect($response->json('data.estates'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /charge-types/{chargeType} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates a charge type with valid data', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.charge.type', $chargeType), [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

it('returns 422 when update code contains invalid characters', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.charge.type', $chargeType), ['code' => 'invalid-code'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('returns 422 when update applies_to is invalid', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.charge.type', $chargeType), ['applies_to' => 'both'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['applies_to']);
});

it('returns 422 when update code exceeds 50 characters', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.charge.type', $chargeType), ['code' => str_repeat('A', 51)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('returns 404 when updating a charge type from another tenant', function () {
    $user            = adminUser();
    $otherChargeType = ChargeType::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.charge.type', $otherChargeType), ['name' => 'Hacked'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /charge-types/{chargeType} (delete single)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single non-system charge type', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id, 'is_system' => false]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.charge.type', $chargeType))
        ->assertOk();

    $this->assertDatabaseMissing('charge_types', ['id' => $chargeType->id]);
});

it('returns 404 when deleting a charge type from another tenant', function () {
    $user            = adminUser();
    $otherChargeType = ChargeType::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.charge.type', $otherChargeType))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /charge-types (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-tenant charge types', function () {
    $user        = adminUser();
    $chargeTypes = ChargeType::factory()->count(3)->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.charge.types'), [
            'charge_type_ids' => $chargeTypes->pluck('id')->all(),
        ])
        ->assertOk();

    $chargeTypes->each(fn ($ct) => $this->assertDatabaseMissing('charge_types', ['id' => $ct->id]));
});

it('returns 422 when bulk delete charge_type_ids is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.charge.types'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_ids']);
});

it('returns 422 when bulk delete charge_type_ids is an empty array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.charge.types'), ['charge_type_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_ids']);
});

it('returns 422 when bulk delete charge_type_ids contains a non-uuid value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.charge.types'), ['charge_type_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_ids.0']);
});
