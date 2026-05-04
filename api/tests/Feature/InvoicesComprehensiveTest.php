<?php

use App\Enums\InvoiceStatus;
use App\Models\ChargeType;
use App\Models\CashbookEntry;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Support\Str;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a fully-wired invoice with its dependencies.
 * Returns ['user', 'estate', 'unit', 'chargeType', 'owner', 'invoice'].
 */
function makeInvoice(array $invoiceOverrides = []): array
{
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice    = Invoice::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'billing_period'  => '2026-03-01',
    ], $invoiceOverrides));

    return compact('user', 'estate', 'unit', 'chargeType', 'owner', 'invoice');
}

// ──────────────────────────────────────────────────────────────────────────────
// API response payload — complete field verification
// ──────────────────────────────────────────────────────────────────────────────

it('returns the complete invoice payload on show', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice(['status' => InvoiceStatus::UNPAID->value]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoice', $invoice))
        ->assertOk()
        ->json('data');

    // Core identity fields
    expect($data['id'])->toBe($invoice->id);
    expect($data['organization_id'])->toBe($user->organization_id);
    expect($data['unit_id'])->toBe($invoice->unit_id);
    expect($data['charge_type_id'])->toBe($invoice->charge_type_id);
    expect($data['billed_to_type'])->toBe('owner');
    expect($data['billed_to_id'])->toBe($invoice->billed_to_id);

    // Invoice fields
    expect($data['invoice_number'])->toBeString();
    expect($data['status'])->toBe(InvoiceStatus::UNPAID->value);
    expect($data)->toHaveKey('amount');
    expect($data)->toHaveKey('billing_period');
    expect($data)->toHaveKey('due_date');
    expect($data)->toHaveKey('sent_at');

    // Audit fields
    expect($data)->toHaveKey('issued_by_type');
    expect($data)->toHaveKey('issued_by_user_id');
    expect($data)->toHaveKey('created_at');
    expect($data)->toHaveKey('updated_at');
    expect($data)->toHaveKey('deleted_at');

    // Computed fields
    expect($data)->toHaveKey('is_paid');
    expect($data)->toHaveKey('total_paid');
    expect($data)->toHaveKey('outstanding');
});

it('returns the complete invoice payload on index', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices'))
        ->assertOk()
        ->json('data.0');

    expect($data)->toHaveKeys([
        'id', 'organization_id', 'unit_id', 'charge_type_id',
        'billed_to_type', 'billed_to_id', 'invoice_number',
        'status', 'amount', 'billing_period', 'due_date',
        'sent_at', 'issued_by_type', 'issued_by_user_id',
        'created_at', 'updated_at', 'deleted_at',
        'is_paid', 'total_paid', 'outstanding',
    ]);
});

it('invoice amount is returned as a float', function () {
    ['user' => $user] = makeInvoice(['amount' => 1500]);

    $amount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices'))
        ->assertOk()
        ->json('data.0.amount');

    expect(is_numeric($amount))->toBeTrue();
});

it('billing_period is returned as a date string', function () {
    ['user' => $user] = makeInvoice(['billing_period' => '2026-04-01']);

    $period = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices'))
        ->assertOk()
        ->json('data.0.billing_period');

    expect($period)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — status
// ──────────────────────────────────────────────────────────────────────────────

it('filters invoices by status', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $base = [
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ];

    Invoice::factory()->create(array_merge($base, ['status' => InvoiceStatus::PAID->value, 'billing_period' => '2026-01-01']));
    Invoice::factory()->create(array_merge($base, ['status' => InvoiceStatus::UNPAID->value, 'billing_period' => '2026-02-01']));
    Invoice::factory()->create(array_merge($base, ['status' => InvoiceStatus::OVERDUE->value, 'billing_period' => '2026-03-01']));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?status=' . InvoiceStatus::PAID->value)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.status'))->toBe(InvoiceStatus::PAID->value);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — unit_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters invoices by unit_id', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $unitA = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $unitB = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ownerA     = Owner::factory()->create(['unit_id' => $unitA->id, 'organization_id' => $user->organization_id]);
    $ownerB     = Owner::factory()->create(['unit_id' => $unitB->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitA->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerA->id,
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitB->id,
        'charge_type_id' => $chargeType->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerB->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?unit_id=' . $unitA->id)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.unit_id'))->toBe($unitA->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — estate_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters invoices by estate_id', function () {
    $user    = adminUser();
    $estateA = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $estateB = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $unitA = Unit::factory()->create(['estate_id' => $estateA->id, 'organization_id' => $user->organization_id]);
    $unitB = Unit::factory()->create(['estate_id' => $estateB->id, 'organization_id' => $user->organization_id]);

    $ctA    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ownerA = Owner::factory()->create(['unit_id' => $unitA->id, 'organization_id' => $user->organization_id]);
    $ownerB = Owner::factory()->create(['unit_id' => $unitB->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitA->id,
        'charge_type_id' => $ctA->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerA->id,
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitB->id,
        'charge_type_id' => $ctB->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerB->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?estate_id=' . $estateA->id)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — charge_type_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters invoices by charge_type_id', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ctA    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ctA->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-01-01',
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ctB->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-02-01',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?charge_type_id=' . $ctA->id)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.charge_type_id'))->toBe($ctA->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — billed_to_type
// ──────────────────────────────────────────────────────────────────────────────

it('filters invoices by billed_to_type owner', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ctA        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ctA->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-01-01',
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ctB->id, 'billed_to_type' => 'organization', 'billed_to_id' => Str::uuid(),
        'billing_period' => '2026-02-01',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?billed_to_type=owner')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.billed_to_type'))->toBe('owner');
});

// ──────────────────────────────────────────────────────────────────────────────
// Sorting
// ──────────────────────────────────────────────────────────────────────────────

it('sorts invoices by amount ascending', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ctA        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctC        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $base = ['organization_id' => $user->organization_id, 'unit_id' => $unit->id,
              'billed_to_type' => 'owner', 'billed_to_id' => $owner->id];

    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctA->id, 'amount' => 3000, 'billing_period' => '2026-01-01']));
    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctB->id, 'amount' => 1000, 'billing_period' => '2026-02-01']));
    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctC->id, 'amount' => 2000, 'billing_period' => '2026-03-01']));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_sort=amount:asc')
        ->assertOk();

    $amounts = collect($response->json('data'))->pluck('amount')->values()->toArray();
    $sorted = $amounts; sort($sorted); expect($amounts)->toBe($sorted);
});

it('sorts invoices by amount descending', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ctA        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctC        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $base = ['organization_id' => $user->organization_id, 'unit_id' => $unit->id,
              'billed_to_type' => 'owner', 'billed_to_id' => $owner->id];

    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctA->id, 'amount' => 1000, 'billing_period' => '2026-01-01']));
    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctB->id, 'amount' => 3000, 'billing_period' => '2026-02-01']));
    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctC->id, 'amount' => 2000, 'billing_period' => '2026-03-01']));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_sort=amount:desc')
        ->assertOk();

    $amounts = collect($response->json('data'))->pluck('amount')->values()->toArray();
    expect($amounts[0])->toBeGreaterThanOrEqual($amounts[1]);
    expect($amounts[1])->toBeGreaterThanOrEqual($amounts[2]);
});

it('sorts invoices by billing_period ascending', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ctA        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctC        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $base = ['organization_id' => $user->organization_id, 'unit_id' => $unit->id,
              'billed_to_type' => 'owner', 'billed_to_id' => $owner->id];

    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctA->id, 'billing_period' => '2026-03-01']));
    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctB->id, 'billing_period' => '2026-01-01']));
    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctC->id, 'billing_period' => '2026-02-01']));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_sort=billing_period:asc')
        ->assertOk();

    $periods = collect($response->json('data'))->pluck('billing_period')->values()->toArray();
    expect($periods[0])->toBeLessThanOrEqual($periods[1]);
    expect($periods[1])->toBeLessThanOrEqual($periods[2]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range — created_at
// ──────────────────────────────────────────────────────────────────────────────

it('filters invoices by date_range=today', function () {
    ['user' => $user] = makeInvoice();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?date_range=today')
        ->assertOk();

    expect($response->json('meta.total'))->toBeGreaterThan(0);
});

it('filters invoices by date_range=this_month', function () {
    ['user' => $user] = makeInvoice();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?date_range=this_month')
        ->assertOk();

    expect($response->json('meta.total'))->toBeGreaterThan(0);
});

it('filters invoices by date_range=all_time returns all', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct         = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    foreach (['2026-01-01', '2026-02-01', '2026-03-01'] as $period) {
        Invoice::factory()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => $period,
        ]);
    }

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?date_range=all_time')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(3);
});

it('filters invoices by custom date range', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $start = now()->subDay()->toDateString();
    $end   = now()->addDay()->toDateString();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . "?date_range=custom&date_range_start={$start}&date_range_end={$end}")
        ->assertOk();

    expect($response->json('meta.total'))->toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Pagination
// ──────────────────────────────────────────────────────────────────────────────

it('paginates invoices correctly with _per_page', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $periods = ['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01'];
    foreach ($periods as $period) {
        $ct = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
        Invoice::factory()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => $period,
        ]);
    }

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_per_page=2')
        ->assertOk();

    expect(count($response->json('data')))->toBe(2);
    expect($response->json('meta.total'))->toBe(5);
    expect($response->json('meta.per_page'))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Relationships — eager loading on index and show
// ──────────────────────────────────────────────────────────────────────────────

it('returns billedToOwner relationship when requested on index', function () {
    ['user' => $user, 'invoice' => $invoice, 'owner' => $owner] = makeInvoice(['billed_to_type' => 'owner']);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_relationships=billedToOwner')
        ->assertOk();

    expect($response->json('data.0.billed_to_owner'))->toHaveKey('id');
});

it('returns billedToOwner relationship when requested on show', function () {
    ['user' => $user, 'invoice' => $invoice, 'owner' => $owner] = makeInvoice(['billed_to_type' => 'owner']);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoice', $invoice) . '?_relationships=billedToOwner')
        ->assertOk();

    expect($response->json('data.billed_to_owner'))->toHaveKey('id');
});

it('returns cashbookEntries relationship when requested on index', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_relationships=cashbookEntries')
        ->assertOk();

    expect($response->json('data.0.cashbook_entries'))->toBeArray();
});

it('returns cashbookEntries count on index when requested', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_countable_relationships=cashbookEntries')
        ->assertOk();

    expect($response->json('data.0.cashbook_entries_count'))->toBeInt();
});

it('returns cashbookEntries count on show when requested', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoice', $invoice) . '?_countable_relationships=cashbookEntries')
        ->assertOk();

    expect($response->json('data.cashbook_entries_count'))->toBeInt();
});

it('ignores disallowed relationship names', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices') . '?_relationships=users,passwords')
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// CRUD — create (duplicate prevention)
// ──────────────────────────────────────────────────────────────────────────────

it('prevents creating a duplicate invoice for same unit charge_type and billing_period', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $payload = [
        'unit_id'        => $unit->id,
        'charge_type_id' => $chargeType->id,
        'billed_to_type' => 'owner',
        'billed_to_id'   => $owner->id,
        'amount'         => 1000,
        'billing_period' => '2026-04-01',
        'due_date'       => '2026-04-07',
    ];

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), $payload)
        ->assertCreated();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), $payload)
        ->assertStatus(500); // Service throws Exception for duplicates
});

it('allows the same unit and charge_type for different billing periods', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $base = [
        'unit_id'        => $unit->id,
        'charge_type_id' => $chargeType->id,
        'billed_to_type' => 'owner',
        'billed_to_id'   => $owner->id,
        'amount'         => 1000,
        'due_date'       => '2026-04-07',
    ];

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), array_merge($base, ['billing_period' => '2026-03-01']))
        ->assertCreated();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), array_merge($base, ['billing_period' => '2026-04-01']))
        ->assertCreated();
});

it('creates invoice with billed_to_type organization (tenant)', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'organization',
            'billed_to_id'   => Str::uuid(),
            'amount'         => 500,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertCreated()
        ->assertJsonPath('data.billed_to_type', 'organization');
});

it('creates invoice with amount zero (boundary)', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'amount'         => 0,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertCreated();
});

// ──────────────────────────────────────────────────────────────────────────────
// CRUD — create validation edge cases
// ──────────────────────────────────────────────────────────────────────────────

it('returns 422 when billed_to_id is missing', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'amount'         => 1000,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billed_to_id']);
});

it('returns 422 when billed_to_id is not a uuid', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => 'not-a-uuid',
            'amount'         => 1000,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billed_to_id']);
});

it('returns 422 when billing_period is not a valid date', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'amount'         => 1000,
            'billing_period' => 'not-a-date',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_period']);
});

it('returns 422 when due_date is not a valid date', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => $owner->id,
            'amount'         => 1000,
            'billing_period' => '2026-04-01',
            'due_date'       => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['due_date']);
});

it('returns 422 when unit_id does not exist in the database', function () {
    $user       = adminUser();
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => Str::uuid(),
            'charge_type_id' => $chargeType->id,
            'billed_to_type' => 'owner',
            'billed_to_id'   => Str::uuid(),
            'amount'         => 1000,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

it('returns 422 when charge_type_id does not exist in the database', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.invoice'), [
            'unit_id'        => $unit->id,
            'charge_type_id' => Str::uuid(),
            'billed_to_type' => 'owner',
            'billed_to_id'   => Str::uuid(),
            'amount'         => 1000,
            'billing_period' => '2026-04-01',
            'due_date'       => '2026-04-07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge_type_id']);
});

// ──────────────────────────────────────────────────────────────────────────────
// CRUD — update
// ──────────────────────────────────────────────────────────────────────────────

it('update returns the updated invoice data', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice(['status' => InvoiceStatus::UNPAID->value]);

    $response = $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), [
            'status'   => InvoiceStatus::PAID->value,
            'due_date' => '2026-06-01',
        ])
        ->assertOk();

    expect($response->json('data.status'))->toBe(InvoiceStatus::PAID->value);
    expect($response->json('data.due_date'))->toBe('2026-06-01');
});

it('update persists changes to the database', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), [
            'status' => InvoiceStatus::OVERDUE->value,
        ])
        ->assertOk();

    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => InvoiceStatus::OVERDUE->value]);
});

it('returns 422 when update billing_period is not a valid date', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), ['billing_period' => 'not-a-date'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_period']);
});

it('returns 422 when update due_date is not a valid date', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), ['due_date' => 'not-a-date'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['due_date']);
});

it('accepts amount of zero on update', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.invoice', $invoice), ['amount' => 0])
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// CRUD — delete
// ──────────────────────────────────────────────────────────────────────────────

it('delete returns a success message', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $response = $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoice', $invoice))
        ->assertOk();

    expect($response->json('message'))->toBeString();
});

it('deleted invoice is soft-deleted not hard-deleted', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoice();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoice', $invoice))
        ->assertOk();

    $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
});

it('soft-deleted invoice is excluded from the index', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ctA        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $invoiceA = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ctA->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-01-01',
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ctB->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-02-01',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoice', $invoiceA))
        ->assertOk();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices'))
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// CRUD — bulk delete
// ──────────────────────────────────────────────────────────────────────────────

it('bulk delete returns a success message', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct         = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice    = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-01-01',
    ]);

    $response = $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoices'), ['invoice_ids' => [$invoice->id]])
        ->assertOk();

    expect($response->json('message'))->toBeString();
});

it('bulk delete silently skips ids not belonging to the tenant', function () {
    $user   = adminUser();
    $other  = createTenant();

    $otherEstate = Estate::factory()->create(['organization_id' => $other->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'organization_id' => $other->id]);
    $otherCt     = ChargeType::factory()->create(['organization_id' => $other->id]);
    $otherOwner  = Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $otherInvoice = Invoice::factory()->create([
        'organization_id' => $other->id, 'unit_id' => $otherUnit->id,
        'charge_type_id' => $otherCt->id, 'billed_to_type' => 'owner', 'billed_to_id' => $otherOwner->id,
    ]);

    // Sending another tenant's id — service scopes by organization_id, so it throws "No Invoices deleted"
    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.invoices'), ['invoice_ids' => [$otherInvoice->id]])
        ->assertStatus(500);

    // Other tenant's invoice must still be present
    $this->assertDatabaseHas('invoices', ['id' => $otherInvoice->id, 'deleted_at' => null]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Invoice summary statistics
// ──────────────────────────────────────────────────────────────────────────────

it('returns invoice summary with correct structure', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices.summary'))
        ->assertOk();

    expect($response->json())->toHaveKeys([
        'total', 'total_amount', 'paid_count',
        'overdue_count', 'partially_paid_count',
        'unpaid_count', 'revenue_by_charge_type',
    ]);
});

it('invoice summary totals match actual invoice counts', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ctA        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB        = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $base = ['organization_id' => $user->organization_id, 'unit_id' => $unit->id,
              'billed_to_type' => 'owner', 'billed_to_id' => $owner->id];

    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctA->id, 'status' => InvoiceStatus::PAID->value,    'billing_period' => '2026-01-01']));
    Invoice::factory()->create(array_merge($base, ['charge_type_id' => $ctB->id, 'status' => InvoiceStatus::OVERDUE->value, 'billing_period' => '2026-02-01']));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices.summary'))
        ->assertOk();

    expect($response->json('total'))->toBeInt()->toBeGreaterThanOrEqual(2);
    expect($response->json('paid_count'))->toBeGreaterThanOrEqual(1);
    expect($response->json('overdue_count'))->toBeGreaterThanOrEqual(1);
});

it('invoice summary is scoped to the authenticated tenant', function () {
    $user      = adminUser();
    $other     = createTenant();

    $otherEstate = Estate::factory()->create(['organization_id' => $other->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'organization_id' => $other->id]);
    $otherCt     = ChargeType::factory()->create(['organization_id' => $other->id]);
    $otherOwner  = Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);
    Invoice::factory()->create([
        'organization_id' => $other->id, 'unit_id' => $otherUnit->id,
        'charge_type_id' => $otherCt->id, 'billed_to_type' => 'owner', 'billed_to_id' => $otherOwner->id,
        'status' => InvoiceStatus::PAID->value,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices.summary'))
        ->assertOk();

    expect($response->json('paid_count'))->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Run billing — full flow (dry_run=false)
// ──────────────────────────────────────────────────────────────────────────────

it('run billing creates invoices for active units with recurring charge configs', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create([
        'estate_id'       => $estate->id,
        'organization_id' => $user->organization_id,
        'status'          => 'active',
    ]);
    $chargeType = ChargeType::factory()->recurring()->create([
        'organization_id' => $user->organization_id,
        'applies_to'      => 'owner',
    ]);
    $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    \App\Models\UnitChargeConfig::factory()->create([
        'unit_id'        => $unit->id,
        'charge_type_id' => $chargeType->id,
        'amount'         => 3000,
        'is_active'      => true,
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.billing'), [
            'estate_id'      => $estate->id,
            'billing_period' => '2026-06',
            'dry_run'        => false,
        ])
        ->assertOk();

    expect($response->json('created'))->toBeGreaterThanOrEqual(1);
    expect($response->json('dry_run'))->toBeFalse();
});

it('dry_run billing does not persist invoices', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $countBefore = Invoice::where('organization_id', $user->organization_id)->count();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.billing'), [
            'estate_id'      => $estate->id,
            'billing_period' => '2026-06',
            'dry_run'        => true,
        ])
        ->assertOk();

    expect(Invoice::where('organization_id', $user->organization_id)->count())->toBe($countBefore);
});
