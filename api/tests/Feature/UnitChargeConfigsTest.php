<?php

use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Unit;
use App\Models\UnitChargeConfig;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all charge config routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.unit.charge.configs',   ['estate' => 'e', 'unit' => 'u']],
    ['post',   'api.v1.create.unit.charge.config',  ['estate' => 'e', 'unit' => 'u']],
    ['delete', 'api.v1.delete.unit.charge.configs', ['estate' => 'e', 'unit' => 'u']],
    ['get',    'api.v1.show.unit.charge.config',    ['estate' => 'e', 'unit' => 'u', 'chargeConfig' => 'c']],
    ['put',    'api.v1.update.unit.charge.config',  ['estate' => 'e', 'unit' => 'u', 'chargeConfig' => 'c']],
    ['delete', 'api.v1.delete.unit.charge.config',  ['estate' => 'e', 'unit' => 'u', 'chargeConfig' => 'c']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit}/charge-configs (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a list of charge configs for a unit', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    UnitChargeConfig::factory()->count(2)->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.configs', [$estate, $unit]))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);

    expect($response->json('meta.total'))->toBe(2);
});

it('returns 404 when listing charge configs for another tenant unit', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.configs', [$otherEstate, $otherUnit]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /charge-configs (index) — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on charge configs index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.configs', [$estate, $unit]) . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
});

it('returns chargeType relationship on charge configs index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.configs', [$estate, $unit]) . '?_relationships=chargeType')
        ->assertOk();

    expect($response->json('data.0.charge_type'))->toHaveKey('id');
});

it('returns unit and chargeType relationships together on charge configs index', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.configs', [$estate, $unit]) . '?_relationships=unit,chargeType')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
    expect($response->json('data.0.charge_type'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /estates/{estate}/units/{unit}/charge-configs (create) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates a charge config for a unit', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->recurring()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]), [
            'charge_type_id' => $chargeType->id,
            'amount'         => 450.00,
            'is_active'      => true,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('unit_charge_configs', [
        'unit_id'        => $unit->id,
        'charge_type_id' => $chargeType->id,
    ]);
});

it('returns 422 when charge_type_id is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]), [
            'amount' => 450,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_id']);
});

it('returns 422 when charge_type_id is not a valid uuid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]), [
            'charge_type_id' => 'not-a-uuid',
            'amount'         => 450,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_id']);
});

it('returns 422 when charge_type_id does not exist', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]), [
            'charge_type_id' => fake()->uuid(),
            'amount'         => 450,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_id']);
});

it('returns 422 when amount is missing', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]), [
            'charge_type_id' => $chargeType->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when amount is negative', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]), [
            'charge_type_id' => $chargeType->id,
            'amount'         => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('allows amount of zero on charge config create', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]), [
            'charge_type_id' => $chargeType->id,
            'amount'         => 0,
        ])
        ->assertCreated();
});

it('allows is_active to be omitted on charge config create', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]), [
            'charge_type_id' => $chargeType->id,
            'amount'         => 150,
        ])
        ->assertCreated();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /charge-configs — _relationships on create response
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship in charge config create response when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]) . '?_relationships=unit', [
            'charge_type_id' => $chargeType->id,
            'amount'         => 200,
        ])
        ->assertCreated();

    expect($response->json('data.unit'))->toHaveKey('id');
});

it('returns chargeType relationship in charge config create response when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.charge.config', [$estate, $unit]) . '?_relationships=chargeType', [
            'charge_type_id' => $chargeType->id,
            'amount'         => 200,
        ])
        ->assertCreated();

    expect($response->json('data.charge_type'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate}/units/{unit}/charge-configs/{chargeConfig} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single charge config', function () {
    $user         = adminUser();
    $estate       = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit         = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType   = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.config', [$estate, $unit, $chargeConfig]))
        ->assertOk()
        ->assertJsonPath('data.id', $chargeConfig->id);
});

it('returns 404 when showing a charge config from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherType   = ChargeType::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $otherUnit->id,
        'tenant_id'      => $otherTenant->id,
        'charge_type_id' => $otherType->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.config', [$otherEstate, $otherUnit, $otherConfig]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /charge-configs/{chargeConfig} — _relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on charge config show when requested', function () {
    $user         = adminUser();
    $estate       = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit         = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType   = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.config', [$estate, $unit, $chargeConfig]) . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.unit'))->toHaveKey('id');
    expect($response->json('data.unit.id'))->toBe($unit->id);
});

it('returns chargeType relationship on charge config show when requested', function () {
    $user         = adminUser();
    $estate       = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit         = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType   = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.charge.config', [$estate, $unit, $chargeConfig]) . '?_relationships=chargeType')
        ->assertOk();

    expect($response->json('data.charge_type'))->toHaveKey('id');
    expect($response->json('data.charge_type.id'))->toBe($chargeType->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /estates/{estate}/units/{unit}/charge-configs/{chargeConfig} (update)
// ──────────────────────────────────────────────────────────────────────────────

it('updates a charge config amount and active status', function () {
    $user         = adminUser();
    $estate       = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit         = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType   = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
        'amount'         => 150.00,
        'is_active'      => true,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.charge.config', [$estate, $unit, $chargeConfig]), [
            'amount'    => 250.00,
            'is_active' => false,
        ])
        ->assertOk();

    $this->assertDatabaseHas('unit_charge_configs', [
        'id'        => $chargeConfig->id,
        'is_active' => false,
    ]);
});

it('returns 422 when update charge_type_id is not a valid uuid', function () {
    $user         = adminUser();
    $estate       = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit         = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType   = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.charge.config', [$estate, $unit, $chargeConfig]), [
            'charge_type_id' => 'not-a-uuid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_id']);
});

it('returns 422 when update amount is negative', function () {
    $user         = adminUser();
    $estate       = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit         = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType   = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.charge.config', [$estate, $unit, $chargeConfig]), [
            'amount' => -50,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 404 when updating a charge config from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherType   = ChargeType::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $otherUnit->id,
        'tenant_id'      => $otherTenant->id,
        'charge_type_id' => $otherType->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.charge.config', [$otherEstate, $otherUnit, $otherConfig]), [
            'amount' => 999,
        ])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /estates/{estate}/units/{unit}/charge-configs/{chargeConfig} (delete)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single charge config', function () {
    $user         = adminUser();
    $estate       = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit         = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType   = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeConfig = UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.charge.config', [$estate, $unit, $chargeConfig]))
        ->assertOk();

    $this->assertDatabaseMissing('unit_charge_configs', ['id' => $chargeConfig->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /estates/{estate}/units/{unit}/charge-configs (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-tenant charge configs', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $configs = UnitChargeConfig::factory()->count(3)->create([
        'unit_id'        => $unit->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.charge.configs', [$estate, $unit]), [
            'charge_config_ids' => $configs->pluck('id')->all(),
        ])
        ->assertOk();

    $configs->each(fn ($c) => $this->assertDatabaseMissing('unit_charge_configs', ['id' => $c->id]));
});

it('returns 422 when bulk delete charge_config_ids is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.charge.configs', [$estate, $unit]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_config_ids']);
});

it('returns 422 when bulk delete charge_config_ids is an empty array', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.charge.configs', [$estate, $unit]), ['charge_config_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_config_ids']);
});

it('returns 422 when bulk delete charge_config_ids contains a non-uuid value', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.charge.configs', [$estate, $unit]), ['charge_config_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_config_ids.0']);
});
