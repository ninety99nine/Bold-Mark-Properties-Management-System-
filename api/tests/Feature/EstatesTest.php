<?php

use App\Models\Estate;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all estate routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.estates'],
    ['get',    'api.v1.show.estates.summary'],
    ['post',   'api.v1.create.estate'],
    ['get',    'api.v1.show.estate',   ['estate' => 'non-existent']],
    ['put',    'api.v1.update.estate', ['estate' => 'non-existent']],
    ['delete', 'api.v1.delete.estate', ['estate' => 'non-existent']],
    ['delete', 'api.v1.delete.estates'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated list of own-tenant estates', function () {
    $user = adminUser();
    Estate::factory()->count(3)->create(['tenant_id' => $user->tenant_id]);
    Estate::factory()->count(2)->create(['tenant_id' => createTenant()->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(3);
});

it('returns an empty list when tenant has no estates', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->assertJson(['data' => []]);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns units relationship on estate index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->count(2)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_relationships=units')
        ->assertOk();

    expect($response->json('data.0.units'))->toBeArray();
    expect(count($response->json('data.0.units')))->toBe(2);
});

it('returns chargeTypes relationship on estate index when requested', function () {
    $user = adminUser();
    Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_relationships=chargeTypes')
        ->assertOk();

    expect($response->json('data.0.charge_types'))->toBeArray();
});

it('returns tenant relationship on estate index when requested', function () {
    $user = adminUser();
    Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_relationships=tenant')
        ->assertOk();

    expect($response->json('data.0.tenant'))->toHaveKey('id');
});

it('returns multiple relationships on estate index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_relationships=units,chargeTypes')
        ->assertOk();

    expect($response->json('data.0.units'))->toBeArray();
    expect($response->json('data.0.charge_types'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates — _countable_relationships (counts)
// ──────────────────────────────────────────────────────────────────────────────

it('returns units_count on estate index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->count(3)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_countable_relationships=units')
        ->assertOk();

    expect($response->json('data.0.units_count'))->toBe(3);
});

it('returns multiple counts on estate index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->count(2)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_countable_relationships=units,invoices')
        ->assertOk();

    expect($response->json('data.0.units_count'))->toBeInt();
    expect($response->json('data.0.invoices_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/summary
// ──────────────────────────────────────────────────────────────────────────────

it('returns estate summary statistics', function () {
    $user = adminUser();
    Estate::factory()->count(2)->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates.summary'))
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /estates (create) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates a new estate with valid data', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'                => 'Crystal Mews Body Corporate',
            'type'                => 'sectional_title',
            'address'             => '12 Acacia Avenue, Gaborone',
            'default_levy_amount' => 2850,
            'billing_day'         => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Crystal Mews Body Corporate')
        ->assertJsonPath('data.type', 'sectional_title');

    $this->assertDatabaseHas('estates', [
        'name'      => 'Crystal Mews Body Corporate',
        'tenant_id' => $user->tenant_id,
    ]);
});

it('returns 422 when estate name is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), ['type' => 'sectional_title'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when estate name exceeds 255 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => str_repeat('x', 256),
            'type' => 'sectional_title',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when estate type is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), ['name' => 'Test Estate'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('returns 422 when estate type is invalid', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => 'Test Estate',
            'type' => 'invalid_type',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('accepts all valid estate type values', function (string $type) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => 'Test Estate',
            'type' => $type,
        ])
        ->assertCreated();
})->with(['sectional_title', 'residential_rental', 'commercial_rental', 'mixed']);

it('returns 422 when default_levy_amount is negative', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'                => 'Test Estate',
            'type'                => 'sectional_title',
            'default_levy_amount' => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_levy_amount']);
});

it('returns 422 when default_rent_amount is negative', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'                => 'Test Estate',
            'type'                => 'residential_rental',
            'default_rent_amount' => -100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_rent_amount']);
});

it('returns 422 when billing_day is less than 1', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'        => 'Test Estate',
            'type'        => 'sectional_title',
            'billing_day' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('returns 422 when billing_day exceeds 28', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'        => 'Test Estate',
            'type'        => 'sectional_title',
            'billing_day' => 29,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('accepts billing_day at boundary values 1 and 28', function (int $day) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'        => 'Test Estate ' . $day,
            'type'        => 'sectional_title',
            'billing_day' => $day,
        ])
        ->assertCreated();
})->with([1, 28]);

it('allows nullable optional fields to be omitted on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => 'Minimal Estate',
            'type' => 'mixed',
        ])
        ->assertCreated();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /estates — _relationships on create response
// ──────────────────────────────────────────────────────────────────────────────

it('returns units relationship in create response when requested', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate') . '?_relationships=units', [
            'name' => 'New Estate',
            'type' => 'sectional_title',
        ])
        ->assertCreated();

    expect($response->json('data.units'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single estate belonging to the user tenant', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $estate))
        ->assertOk()
        ->assertJsonPath('data.id', $estate->id);
});

it('returns 404 when trying to access another tenant estate', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $otherEstate))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /estates/{estate} — _relationships and _countable_relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns units relationship on estate show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->count(2)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $estate) . '?_relationships=units')
        ->assertOk();

    expect($response->json('data.units'))->toBeArray();
    expect(count($response->json('data.units')))->toBe(2);
});

it('returns chargeTypes relationship on estate show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $estate) . '?_relationships=chargeTypes')
        ->assertOk();

    expect($response->json('data.charge_types'))->toBeArray();
});

it('returns tenant relationship on estate show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $estate) . '?_relationships=tenant')
        ->assertOk();

    expect($response->json('data.tenant'))->toHaveKey('id');
});

it('returns units_count on estate show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    Unit::factory()->count(4)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $estate) . '?_countable_relationships=units')
        ->assertOk();

    expect($response->json('data.units_count'))->toBe(4);
});

it('returns multiple counts on estate show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $estate) . '?_countable_relationships=units,invoices,cashbookEntries')
        ->assertOk();

    expect($response->json('data.units_count'))->toBeInt();
    expect($response->json('data.invoices_count'))->toBeInt();
    expect($response->json('data.cashbook_entries_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /estates/{estate} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates an estate with valid data', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), [
            'name' => 'Updated Estate Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Estate Name');

    $this->assertDatabaseHas('estates', ['id' => $estate->id, 'name' => 'Updated Estate Name']);
});

it('allows partial update — only provided fields are changed', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'tenant_id'   => $user->tenant_id,
        'name'        => 'Original Name',
        'billing_day' => 5,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['billing_day' => 15])
        ->assertOk()
        ->assertJsonPath('data.billing_day', 15);

    $this->assertDatabaseHas('estates', ['id' => $estate->id, 'name' => 'Original Name', 'billing_day' => 15]);
});

it('returns 422 when update type is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['type' => 'bad_type'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('returns 422 when update billing_day is below 1', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['billing_day' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('returns 422 when update billing_day exceeds 28', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['billing_day' => 30])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('returns 422 when update name exceeds 255 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['name' => str_repeat('x', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when update default_levy_amount is negative', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['default_levy_amount' => -50])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_levy_amount']);
});

it('returns 404 when updating another tenant estate', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $otherEstate), ['name' => 'Hacked'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /estates/{estate} (delete single)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single estate', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estate', $estate))
        ->assertOk();

    $this->assertDatabaseMissing('estates', ['id' => $estate->id]);
});

it('returns 404 when deleting another tenant estate', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['tenant_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estate', $otherEstate))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /estates (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-tenant estates', function () {
    $user    = adminUser();
    $estates = Estate::factory()->count(3)->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), [
            'estate_ids' => $estates->pluck('id')->all(),
        ])
        ->assertOk();

    $estates->each(fn ($e) => $this->assertDatabaseMissing('estates', ['id' => $e->id]));
});

it('returns 422 when bulk delete estate_ids is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_ids']);
});

it('returns 422 when bulk delete estate_ids is an empty array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), ['estate_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_ids']);
});

it('returns 422 when bulk delete estate_ids contains a non-uuid value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), ['estate_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_ids.0']);
});
