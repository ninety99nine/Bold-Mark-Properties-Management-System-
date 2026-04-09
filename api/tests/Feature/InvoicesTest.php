<?php

use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on all invoice routes when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.invoices'],
    ['get',    'api.v1.show.invoices.summary'],
    ['post',   'api.v1.create.invoice'],
    ['post',   'api.v1.run.billing'],
    ['post',   'api.v1.create.adhoc.billing'],
    ['delete', 'api.v1.delete.invoices'],
    ['get',    'api.v1.show.invoice',   ['invoice' => 'non-existent']],
    ['put',    'api.v1.update.invoice', ['invoice' => 'non-existent']],
    ['delete', 'api.v1.delete.invoice', ['invoice' => 'non-existent']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /invoices (index)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated list of invoices scoped to tenant', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();

    Invoice::factory()->count(3)->create([
        'tenant_id'      => $user->tenant_id,
        'unit_id'        => $unit->id,
        'charge_type_id' => $chargeType->id,
        'billed_to_type' => 'owner',
        'billed_to_id'   => $owner->id,
    ]);

    // Another tenant's invoices — must NOT appear
    $otherTenant    = createTenant();
    $otherEstate    = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit      = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherChargeType = ChargeType::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherOwner     = Owner::where('unit_id', $otherUnit->id)->firstOrFail();
    Invoice::factory()->count(2)->create([
        'tenant_id'      => $otherTenant->id,
        'unit_id'        => $otherUnit->id,
        'charge_type_id' => $otherChargeType->id,
        'billed_to_type' => 'owner',
        'billed_to_id'   => $otherOwner->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /invoices — _relationships (eager loading)
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on invoices index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
});

it('returns chargeType relationship on invoices index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_relationships=chargeType')
        ->assertOk();

    expect($response->json('data.0.charge_type'))->toHaveKey('id');
});

it('returns multiple relationships on invoices index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_relationships=unit,chargeType')
        ->assertOk();

    expect($response->json('data.0.unit'))->toHaveKey('id');
    expect($response->json('data.0.charge_type'))->toHaveKey('id');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /invoices — _countable_relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns cashbook_entries_count on invoices index when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_countable_relationships=cashbookEntries')
        ->assertOk();

    expect($response->json('data.0.cashbook_entries_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /invoices/summary
// ──────────────────────────────────────────────────────────────────────────────

it('returns invoice summary statistics', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices.summary'))
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /invoices/{invoice} (show)
// ──────────────────────────────────────────────────────────────────────────────

it('returns a single invoice belonging to the user tenant', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoice', $invoice))
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id);
});

it('returns 404 when showing an invoice from another tenant', function () {
    $user         = adminUser();
    $otherTenant  = createTenant();
    $otherEstate  = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit    = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherCharge  = ChargeType::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherOwner   = Owner::where('unit_id', $otherUnit->id)->firstOrFail();
    $otherInvoice = Invoice::factory()->create([
        'tenant_id' => $otherTenant->id, 'unit_id' => $otherUnit->id,
        'charge_type_id' => $otherCharge->id, 'billed_to_type' => 'owner', 'billed_to_id' => $otherOwner->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoice', $otherInvoice))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /invoices/{invoice} — _relationships and _countable_relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns unit relationship on invoice show when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoice', $invoice) . '?_relationships=unit')
        ->assertOk();

    expect($response->json('data.unit'))->toHaveKey('id');
});

it('returns cashbook_entries_count on invoice show when requested', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoice', $invoice) . '?_countable_relationships=cashbookEntries')
        ->assertOk();

    expect($response->json('data.cashbook_entries_count'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /invoices (create) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates an invoice with valid data', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'amount'         => 2850,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertCreated()
        ->assertJsonPath('data.amount', 2850.0);
});

it('returns 422 when unit_id is missing', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => \Illuminate\Support\Str::uuid(),
            'amount'         => 2850,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

it('returns 422 when unit_id is not a valid uuid', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => 'not-a-uuid',
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => \Illuminate\Support\Str::uuid(),
            'amount'         => 2850,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

it('returns 422 when charge_type_id is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $owner  = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'amount'         => 2850,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_id']);
});

it('returns 422 when billed_to_type is missing', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_id'   => $owner->id,
            'amount'         => 2850,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billed_to_type']);
});

it('returns 422 when billed_to_type is invalid', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'company',
            'billed_to_id'   => $owner->id,
            'amount'         => 2850,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billed_to_type']);
});

it('returns 422 when amount is missing', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when amount is negative', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'amount'         => -100,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when billing_period is missing', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'amount'         => 2850,
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_period']);
});

it('returns 422 when due_date is missing', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'amount'         => 2850,
            'billing_period' => '2026-04-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['due_date']);
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /invoices/{invoice} (update) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('updates an invoice with valid data', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), [
            'due_date' => '2026-05-15',
        ])
        ->assertOk();
});

it('returns 422 when update status is invalid', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), ['status' => 'invalid_status'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('accepts all valid invoice status values on update', function (string $status) {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), ['status' => $status])
        ->assertOk();
})->with(['draft', 'sent', 'paid', 'partially_paid', 'overdue']);

it('returns 422 when update amount is negative', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), ['amount' => -50])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 404 when updating an invoice from another tenant', function () {
    $user         = adminUser();
    $otherTenant  = createTenant();
    $otherEstate  = Estate::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherUnit    = Unit::factory()->create(['estate_id' => $otherEstate->id, 'tenant_id' => $otherTenant->id]);
    $otherCharge  = ChargeType::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherOwner   = Owner::where('unit_id', $otherUnit->id)->firstOrFail();
    $otherInvoice = Invoice::factory()->create([
        'tenant_id' => $otherTenant->id, 'unit_id' => $otherUnit->id,
        'charge_type_id' => $otherCharge->id, 'billed_to_type' => 'owner', 'billed_to_id' => $otherOwner->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $otherInvoice), ['status' => 'paid'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /invoices/run-billing — validation
// ──────────────────────────────────────────────────────────────────────────────

it('returns 422 when run_billing estate_id is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.billing'), [
            'billing_period' => '2026-04',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_id']);
});

it('returns 422 when run_billing estate_id is not a uuid', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.billing'), [
            'estate_id'      => 'not-uuid',
            'billing_period' => '2026-04',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_id']);
});

it('returns 422 when run_billing billing_period is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.billing'), ['estate_id' => $estate->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_period']);
});

it('returns 422 when run_billing billing_period format is wrong', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.billing'), [
            'estate_id'      => $estate->id,
            'billing_period' => '04-2026',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_period']);
});

it('accepts dry_run flag on run_billing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.billing'), [
            'estate_id'      => $estate->id,
            'billing_period' => '2026-04',
            'dry_run'        => true,
        ])
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /invoices/adhoc-billing — validation
// ──────────────────────────────────────────────────────────────────────────────

it('creates adhoc billing with valid data', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->adHoc()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.adhoc.billing'), [
            'estate_id'      => $estate->id,
            'charge_type_id' => $chargeType->id,
            'amount'         => 500,
            'billing_period' => '2026-04',
        ])
        ->assertOk();
});

it('returns 422 when adhoc_billing estate_id is missing', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.adhoc.billing'), [
            'charge_type_id' => $chargeType->id,
            'amount'         => 500,
            'billing_period' => '2026-04',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_id']);
});

it('returns 422 when adhoc_billing charge_type_id is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.adhoc.billing'), [
            'estate_id'      => $estate->id,
            'amount'         => 500,
            'billing_period' => '2026-04',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_id']);
});

it('returns 422 when adhoc_billing amount is missing', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.adhoc.billing'), [
            'estate_id'      => $estate->id,
            'charge_type_id' => $chargeType->id,
            'billing_period' => '2026-04',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when adhoc_billing amount is negative', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.adhoc.billing'), [
            'estate_id'      => $estate->id,
            'charge_type_id' => $chargeType->id,
            'amount'         => -100,
            'billing_period' => '2026-04',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when adhoc_billing billing_period format is wrong', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.adhoc.billing'), [
            'estate_id'      => $estate->id,
            'charge_type_id' => $chargeType->id,
            'amount'         => 500,
            'billing_period' => '2026-04-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_period']);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /invoices/{invoice} (delete single)
// ──────────────────────────────────────────────────────────────────────────────

it('deletes a single invoice', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoice    = Invoice::factory()->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoice', $invoice))
        ->assertOk();

    $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /invoices (bulk delete) — validation
// ──────────────────────────────────────────────────────────────────────────────

it('bulk deletes own-tenant invoices', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'tenant_id' => $user->tenant_id]);
    $chargeType = ChargeType::factory()->create(['tenant_id' => $user->tenant_id]);
    $owner      = Owner::where('unit_id', $unit->id)->firstOrFail();
    $invoices   = Invoice::factory()->count(3)->create([
        'tenant_id' => $user->tenant_id, 'unit_id' => $unit->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoices'), [
            'invoice_ids' => $invoices->pluck('id')->all(),
        ])
        ->assertOk();

    $invoices->each(fn ($i) => $this->assertDatabaseMissing('invoices', ['id' => $i->id]));
});

it('returns 422 when bulk delete invoice_ids is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoices'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoice_ids']);
});

it('returns 422 when bulk delete invoice_ids is an empty array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoices'), ['invoice_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoice_ids']);
});

it('returns 422 when bulk delete invoice_ids contains a non-uuid value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoices'), ['invoice_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoice_ids.0']);
});
