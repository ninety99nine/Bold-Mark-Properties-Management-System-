<?php

use App\Models\Ledger;
use App\Models\Community;
use App\Models\Unit;
use App\Models\UnitLedgerConfig;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all ledger config routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.unit.ledger.configs',   ['community' => 'e', 'unit' => 'u']],
    ['post',   'api.v1.create.unit.ledger.config',  ['community' => 'e', 'unit' => 'u']],
    ['delete', 'api.v1.delete.unit.ledger.configs', ['community' => 'e', 'unit' => 'u']],
    ['get',    'api.v1.show.unit.ledger.config',    ['community' => 'e', 'unit' => 'u', 'ledgerConfig' => 'c']],
    ['put',    'api.v1.update.unit.ledger.config',  ['community' => 'e', 'unit' => 'u', 'ledgerConfig' => 'c']],
    ['delete', 'api.v1.delete.unit.ledger.config',  ['community' => 'e', 'unit' => 'u', 'ledgerConfig' => 'c']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /communities/{community}/units/{unit}/ledger-configs (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a list of ledger configs for a unit', function () {
    $user        = adminUser();
    $community      = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger1 = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledger2 = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    UnitLedgerConfig::factory()->create(['unit_id' => $unit->id, 'ledger_id' => $ledger1->id]);
    UnitLedgerConfig::factory()->create(['unit_id' => $unit->id, 'ledger_id' => $ledger2->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.configs', [$community, $unit]))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);

    expect($response->json('meta.total'))->toBe(2);
});

it('returns 404 when listing ledger configs for another occupant unit', function () {
    $user        = adminUser();
    $otherOccupant = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $otherOccupant->id]);
    $otherUnit   = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $otherOccupant->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.configs', [$otherCommunity, $otherUnit]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /ledger-configs (index) — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on ledger configs index when requested', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.configs', [$community, $unit]) . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
});

it('returns ledger relationship on ledger configs index when requested', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.configs', [$community, $unit]) . '?_relationships=ledger')
        ->assertOk();

    expect($response->json('data.0.ledger'))->toHaveKey('id');
});

it('returns unit and ledger relationships together on ledger configs index', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.configs', [$community, $unit]) . '?_relationships=unit,ledger')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
    expect($response->json('data.0.ledger'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /communities/{community}/units/{unit}/ledger-configs (create) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates a ledger config for a unit', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->recurring()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]), [
            'ledger_id' => $ledger->id,
            'amount'         => 450.00,
            'is_active'      => true,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('unit_ledger_configs', [
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);
});

it('returns 422 when ledger_id is missing', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]), [
            'amount' => 450,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_id']);
});

it('returns 422 when ledger_id is not a valid uuid', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]), [
            'ledger_id' => 'not-a-uuid',
            'amount'         => 450,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_id']);
});

it('returns 422 when ledger_id does not exist', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]), [
            'ledger_id' => fake()->uuid(),
            'amount'         => 450,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_id']);
});

it('returns 422 when amount is missing', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]), [
            'ledger_id' => $ledger->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when amount is negative', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]), [
            'ledger_id' => $ledger->id,
            'amount'         => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('allows amount of zero on ledger config create', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]), [
            'ledger_id' => $ledger->id,
            'amount'         => 0,
        ])
        ->assertCreated();
});

it('allows is_active to be omitted on ledger config create', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]), [
            'ledger_id' => $ledger->id,
            'amount'         => 150,
        ])
        ->assertCreated();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /ledger-configs — _relationships on create response
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship in ledger config create response when requested', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]) . '?_relationships=unit', [
            'ledger_id' => $ledger->id,
            'amount'         => 200,
        ])
        ->assertCreated();

    expect($response->json('data.unit'))->toHaveKey('id');
});

it('returns ledger relationship in ledger config create response when requested', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit.ledger.config', [$community, $unit]) . '?_relationships=ledger', [
            'ledger_id' => $ledger->id,
            'amount'         => 200,
        ])
        ->assertCreated();

    expect($response->json('data.ledger'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /communities/{community}/units/{unit}/ledger-configs/{ledgerConfig} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single ledger config', function () {
    $user         = adminUser();
    $community       = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit         = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.config', [$community, $unit, $ledgerConfig]))
        ->assertOk()
        ->assertJsonPath('data.id', $ledgerConfig->id);
});

it('returns 404 when showing a ledger config from another occupant', function () {
    $user        = adminUser();
    $otherOccupant = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $otherOccupant->id]);
    $otherUnit   = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $otherOccupant->id]);
    $otherType   = Ledger::factory()->create(['organization_id' => $otherOccupant->id]);
    $otherConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $otherUnit->id,
        'ledger_id' => $otherType->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.config', [$otherCommunity, $otherUnit, $otherConfig]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /ledger-configs/{ledgerConfig} — _relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on ledger config show when requested', function () {
    $user         = adminUser();
    $community       = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit         = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.config', [$community, $unit, $ledgerConfig]) . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.unit'))->toHaveKey('id');
    expect($response->json('data.unit.id'))->toBe($unit->id);
});

it('returns ledger relationship on ledger config show when requested', function () {
    $user         = adminUser();
    $community       = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit         = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.ledger.config', [$community, $unit, $ledgerConfig]) . '?_relationships=ledger')
        ->assertOk();

    expect($response->json('data.ledger'))->toHaveKey('id');
    expect($response->json('data.ledger.id'))->toBe($ledger->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /communities/{community}/units/{unit}/ledger-configs/{ledgerConfig} (update)
// ──────────────────────────────────────────────────────────────────────────────

it('updates a ledger config amount and active status', function () {
    $user         = adminUser();
    $community       = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit         = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
        'amount'         => 150.00,
        'is_active'      => true,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.ledger.config', [$community, $unit, $ledgerConfig]), [
            'amount'    => 250.00,
            'is_active' => false,
        ])
        ->assertOk();

    $this->assertDatabaseHas('unit_ledger_configs', [
        'id'        => $ledgerConfig->id,
        'is_active' => false,
    ]);
});

it('returns 422 when update ledger_id is not a valid uuid', function () {
    $user         = adminUser();
    $community       = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit         = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.ledger.config', [$community, $unit, $ledgerConfig]), [
            'ledger_id' => 'not-a-uuid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_id']);
});

it('returns 422 when update amount is negative', function () {
    $user         = adminUser();
    $community       = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit         = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.ledger.config', [$community, $unit, $ledgerConfig]), [
            'amount' => -50,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 404 when updating a ledger config from another occupant', function () {
    $user        = adminUser();
    $otherOccupant = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $otherOccupant->id]);
    $otherUnit   = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $otherOccupant->id]);
    $otherType   = Ledger::factory()->create(['organization_id' => $otherOccupant->id]);
    $otherConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $otherUnit->id,
        'ledger_id' => $otherType->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit.ledger.config', [$otherCommunity, $otherUnit, $otherConfig]), [
            'amount' => 999,
        ])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /communities/{community}/units/{unit}/ledger-configs/{ledgerConfig} (delete)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single ledger config', function () {
    $user         = adminUser();
    $community       = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit         = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerConfig = UnitLedgerConfig::factory()->create([
        'unit_id'        => $unit->id,
        'ledger_id' => $ledger->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.ledger.config', [$community, $unit, $ledgerConfig]))
        ->assertOk();

    $this->assertDatabaseMissing('unit_ledger_configs', ['id' => $ledgerConfig->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /communities/{community}/units/{unit}/ledger-configs (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-occupant ledger configs', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $configs = collect([
        Ledger::factory()->create(['organization_id' => $user->organization_id]),
        Ledger::factory()->create(['organization_id' => $user->organization_id]),
        Ledger::factory()->create(['organization_id' => $user->organization_id]),
    ])->map(fn($ct) => UnitLedgerConfig::factory()->create(['unit_id' => $unit->id, 'ledger_id' => $ct->id]));

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.ledger.configs', [$community, $unit]), [
            'ledger_config_ids' => $configs->pluck('id')->all(),
        ])
        ->assertOk();

    $configs->each(fn ($c) => $this->assertDatabaseMissing('unit_ledger_configs', ['id' => $c->id]));
});

it('returns 422 when bulk delete ledger_config_ids is missing', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.ledger.configs', [$community, $unit]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_config_ids']);
});

it('returns 422 when bulk delete ledger_config_ids is an empty array', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.ledger.configs', [$community, $unit]), ['ledger_config_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_config_ids']);
});

it('returns 422 when bulk delete ledger_config_ids contains a non-uuid value', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.ledger.configs', [$community, $unit]), ['ledger_config_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_config_ids.0']);
});
