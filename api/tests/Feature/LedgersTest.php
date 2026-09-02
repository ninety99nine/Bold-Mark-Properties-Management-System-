<?php

use App\Models\Ledger;
use App\Models\Community;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all ledger routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.ledgers'],
    ['post',   'api.v1.create.ledger'],
    ['get',    'api.v1.show.ledger',   ['ledger' => 'non-existent']],
    ['put',    'api.v1.update.ledger', ['ledger' => 'non-existent']],
    ['delete', 'api.v1.delete.ledger', ['ledger' => 'non-existent']],
    ['delete', 'api.v1.delete.ledgers'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /ledgers (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated list of ledgers scoped to occupant', function () {
    $user = adminUser();

    // The organisation is seeded with the full chart of accounts, so scope the
    // assertion to the exact own-org ledger count vs the other org's ledgers.
    $ownBaseline = Ledger::where('organization_id', $user->organization_id)->count();

    Ledger::factory()->count(3)->create(['organization_id' => $user->organization_id]);
    Ledger::factory()->count(2)->create(['organization_id' => createOrganization()->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.ledgers'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe($ownBaseline + 3);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /ledgers — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns communities relationship on ledgers index when requested', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $community->ledgers()->attach($ledger->id);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.ledgers') . '?_relationships=communities')
        ->assertOk();

    expect($response->json('data.0.communities'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /ledgers/{ledger} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single ledger belonging to the user occupant', function () {
    $user       = adminUser();
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.ledger', $ledger))
        ->assertOk()
        ->assertJsonPath('data.id', $ledger->id);
});

it('returns 404 when showing a ledger from another occupant', function () {
    $user            = adminUser();
    $otherLedger = Ledger::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.ledger', $otherLedger))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /ledgers/{ledger} — _relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns communities relationship on ledger show when requested', function () {
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $community->ledgers()->attach($ledger->id);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.ledger', $ledger) . '?_relationships=communities')
        ->assertOk();

    expect($response->json('data.communities'))->toBeArray();
    expect(count($response->json('data.communities')))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /ledgers (create) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates a ledger with valid data', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.ledger'), [
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Generator Fee');

    $this->assertDatabaseHas('ledgers', [
        'name'            => 'Generator Fee',
        'organization_id' => $user->organization_id,
    ]);
});

it('returns 422 when ledger name is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.ledger'), [
            'applies_to'   => 'either',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when ledger name exceeds 255 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.ledger'), [
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
        ->postJson(route('api.v1.create.ledger'), [
            'name'         => 'Generator Fee',
            'is_recurring' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['applies_to']);
});

it('returns 422 when applies_to is invalid', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.ledger'), [
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
        ->postJson(route('api.v1.create.ledger'), [
            'name'         => 'Fee for ' . $appliesTo,
            'applies_to'   => $appliesTo,
            'is_recurring' => false,
        ])
        ->assertCreated();
})->with(['owner', 'occupant', 'either']);

it('returns 422 when is_recurring is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.ledger'), [
            'name'       => 'Generator Fee',
            'applies_to' => 'either',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_recurring']);
});

it('returns 422 when sort_order is less than 1', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.ledger'), [
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
        ->postJson(route('api.v1.create.ledger'), [
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => false,
        ])
        ->assertCreated();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /ledgers — _relationships on create response
// ──────────────────────────────────────────────────────────────────────────────

it('returns communities relationship in ledger create response when requested', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.ledger') . '?_relationships=communities', [
            'name'         => 'Generator Fee',
            'applies_to'   => 'either',
            'is_recurring' => false,
        ])
        ->assertCreated();

    expect($response->json('data.communities'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /ledgers/{ledger} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates a ledger with valid data', function () {
    $user       = adminUser();
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.ledger', $ledger), [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

it('returns 422 when update applies_to is invalid', function () {
    $user       = adminUser();
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.ledger', $ledger), ['applies_to' => 'both'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['applies_to']);
});

it('returns 404 when updating a ledger from another occupant', function () {
    $user            = adminUser();
    $otherLedger = Ledger::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.ledger', $otherLedger), ['name' => 'Hacked'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /ledgers/{ledger} (delete single)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single non-system ledger', function () {
    $user       = adminUser();
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id, 'is_system' => false]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.ledger', $ledger))
        ->assertOk();

    $this->assertDatabaseMissing('ledgers', ['id' => $ledger->id]);
});

it('returns 404 when deleting a ledger from another occupant', function () {
    $user            = adminUser();
    $otherLedger = Ledger::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.ledger', $otherLedger))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /ledgers (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-occupant ledgers', function () {
    $user        = adminUser();
    $ledgers = Ledger::factory()->count(3)->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.ledgers'), [
            'ledger_ids' => $ledgers->pluck('id')->all(),
        ])
        ->assertOk();

    $ledgers->each(fn ($ct) => $this->assertDatabaseMissing('ledgers', ['id' => $ct->id]));
});

it('returns 422 when bulk delete ledger_ids is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.ledgers'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_ids']);
});

it('returns 422 when bulk delete ledger_ids is an empty array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.ledgers'), ['ledger_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_ids']);
});

it('returns 422 when bulk delete ledger_ids contains a non-uuid value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.ledgers'), ['ledger_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_ids.0']);
});
