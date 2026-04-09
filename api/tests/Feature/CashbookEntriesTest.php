<?php

use App\Models\CashbookEntry;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all cashbook routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.cashbook.entries'],
    ['get',    'api.v1.show.cashbook.summary'],
    ['post',   'api.v1.create.cashbook.entry'],
    ['delete', 'api.v1.delete.cashbook.entries'],
    ['get',    'api.v1.show.cashbook.entry',      ['cashbookEntry' => 'non-existent']],
    ['put',    'api.v1.update.cashbook.entry',     ['cashbookEntry' => 'non-existent']],
    ['post',   'api.v1.allocate.cashbook.entry',   ['cashbookEntry' => 'non-existent']],
    ['delete', 'api.v1.delete.cashbook.entry',     ['cashbookEntry' => 'non-existent']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /cashbook (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated list of cashbook entries scoped to tenant', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    CashbookEntry::factory()->count(3)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    CashbookEntry::factory()->count(2)->create([
        'estate_id' => Estate::factory()->create(['tenant_id' => createTenant()->id])->id,
        'tenant_id' => createTenant()->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /cashbook — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns estate relationship on cashbook entries index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_relationships=estate')
        ->assertOk();

    expect($response->json('data.0.estate'))->toHaveKey('id');
    expect($response->json('data.0.estate.id'))->toBe($estate->id);
});

it('returns unit relationship on cashbook entries index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
});

it('returns chargeType relationship on cashbook entries index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    CashbookEntry::factory()->create([
        'estate_id'      => $estate->id,
        'tenant_id'      => $user->tenant_id,
        'charge_type_id' => $chargeType->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_relationships=chargeType')
        ->assertOk();

    expect($response->json('data.0.charge_type'))->toHaveKey('id');
});

it('returns invoice relationship on cashbook entries index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);
    CashbookEntry::factory()->create([
        'estate_id'  => $estate->id,
        'tenant_id'  => $user->tenant_id,
        'invoice_id' => $invoice->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_relationships=invoice')
        ->assertOk();

    expect($response->json('data.0.invoice'))->toHaveKey('id');
});

it('returns multiple relationships on cashbook entries index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_relationships=estate,childEntries')
        ->assertOk();

    expect($response->json('data.0.estate'))->toHaveKey('id');
    expect($response->json('data.0.child_entries'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /cashbook/summary
// ──────────────────────────────────────────────────────────────────────────────

it('returns cashbook summary statistics', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.summary'))
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /cashbook (create) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates a credit cashbook entry with valid data', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'EFT – Sarah van der Merwe Levy Apr',
            'amount'      => 2850,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'credit');
});

it('creates a debit cashbook entry with valid data', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-06',
            'type'        => 'debit',
            'description' => 'Security Services – March',
            'amount'      => 12500,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'debit');
});

it('returns 422 when estate_id is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_id']);
});

it('returns 422 when estate_id is not a valid uuid', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => 'not-uuid',
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_id']);
});

it('returns 422 when date is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('returns 422 when type is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'description' => 'Test',
            'amount'      => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('returns 422 when type is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'income',
            'description' => 'Test',
            'amount'      => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('accepts all valid cashbook entry type values', function (string $type) {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => $type,
            'description' => 'Test entry',
            'amount'      => 100,
        ])
        ->assertCreated();
})->with(['credit', 'debit']);

it('returns 422 when description is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id' => $estate->id,
            'date'      => '2026-04-02',
            'type'      => 'credit',
            'amount'    => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

it('returns 422 when description exceeds 500 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => str_repeat('x', 501),
            'amount'      => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

it('returns 422 when amount is zero', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when amount is negative', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => -100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when notes exceeds 1000 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
            'notes'       => str_repeat('x', 1001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['notes']);
});

it('returns 422 when unit_id is not a valid uuid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
            'unit_id'     => 'not-uuid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

it('returns 422 when invoice_id is not a valid uuid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
            'invoice_id'  => 'not-uuid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoice_id']);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /cashbook — _relationships on create response
// ──────────────────────────────────────────────────────────────────────────────

it('returns estate relationship in cashbook entry create response when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry') . '?_relationships=estate', [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
        ])
        ->assertCreated();

    expect($response->json('data.estate'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /cashbook/{cashbookEntry} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single cashbook entry belonging to the user tenant', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entry', $entry))
        ->assertOk()
        ->assertJsonPath('data.id', $entry->id);
});

it('returns 404 when showing a cashbook entry from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherEntry  = CashbookEntry::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entry', $otherEntry))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /cashbook/{cashbookEntry} — _relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns estate relationship on cashbook entry show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entry', $entry) . '?_relationships=estate')
        ->assertOk();

    expect($response->json('data.estate'))->toHaveKey('id');
});

it('returns childEntries relationship on cashbook entry show when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entry', $entry) . '?_relationships=childEntries')
        ->assertOk();

    expect($response->json('data.child_entries'))->toBeArray();
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /cashbook/{cashbookEntry} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates a cashbook entry with valid data', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), [
            'description' => 'Updated description',
        ])
        ->assertOk()
        ->assertJsonPath('data.description', 'Updated description');
});

it('returns 422 when update type is invalid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), ['type' => 'invalid_type'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('returns 422 when update description exceeds 500 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), ['description' => str_repeat('x', 501)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

it('returns 422 when update amount is zero', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), ['amount' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when update notes exceeds 1000 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), ['notes' => str_repeat('x', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['notes']);
});

it('returns 422 when update unit_id is not a valid uuid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), ['unit_id' => 'not-uuid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

it('returns 404 when updating a cashbook entry from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherEntry  = CashbookEntry::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $otherEntry), ['description' => 'Hacked'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /cashbook/{cashbookEntry}/allocate — validation
// ──────────────────────────────────────────────────────────────────────────────

it('allocates a cashbook entry to an invoice', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'amount' => 100,
    ]);
    $entry = CashbookEntry::factory()->unallocated()->create([
        'estate_id' => $estate->id,
        'tenant_id' => $user->tenant_id,
        'amount'    => 100,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => $unit->id,
        ])
        ->assertOk();
});

it('returns 422 when allocate invoice_id is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'unit_id' => $unit->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoice_id']);
});

it('returns 422 when allocate unit_id is missing', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);
    $entry = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoice->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

it('returns 422 when allocate invoice_id is not a uuid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => 'not-a-uuid',
            'unit_id'    => $unit->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoice_id']);
});

it('returns 422 when allocate unit_id is not a uuid', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);
    $entry = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => 'not-a-uuid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /cashbook/{cashbookEntry} (delete single)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single cashbook entry', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entry  = CashbookEntry::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entry', $entry))
        ->assertOk();

    $this->assertDatabaseMissing('cashbook_entries', ['id' => $entry->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /cashbook (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-tenant cashbook entries', function () {
    $user    = adminUser();
    $estate  = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $entries = CashbookEntry::factory()->count(3)->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entries'), [
            'entry_ids' => $entries->pluck('id')->all(),
        ])
        ->assertOk();

    $entries->each(fn ($e) => $this->assertDatabaseMissing('cashbook_entries', ['id' => $e->id]));
});

it('returns 422 when bulk delete entry_ids is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entries'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entry_ids']);
});

it('returns 422 when bulk delete entry_ids is an empty array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entries'), ['entry_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entry_ids']);
});

it('returns 422 when bulk delete entry_ids contains a non-uuid value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entries'), ['entry_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entry_ids.0']);
});
