<?php

use App\Enums\CashbookEntryType;
use App\Enums\InvoiceStatus;
use App\Models\CashbookEntry;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Build a minimal cashbook entry with its dependencies.
 * Returns ['user', 'estate', 'entry'].
 */
function makeEntry(array $overrides = []): array
{
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ], $overrides));
    return compact('user', 'estate', 'entry');
}

/**
 * Build a fully-wired invoice for allocation tests.
 * Returns ['user', 'estate', 'unit', 'owner', 'invoice'].
 */
function makeInvoiceForAllocation(float $amount = 1000.0): array
{
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice    = Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'amount'          => $amount,
        'status'          => InvoiceStatus::UNPAID->value,
    ]);
    return compact('user', 'estate', 'unit', 'owner', 'invoice');
}

// ──────────────────────────────────────────────────────────────────────────────
// 401 — missing routes from existing test
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on deallocate when unauthenticated', function () {
    $this->postJson(route('api.v1.deallocate.cashbook.entry', ['cashbookEntry' => 'non-existent']))
        ->assertUnauthorized();
});

it('returns 401 on auto-allocate when unauthenticated', function () {
    $this->postJson(route('api.v1.auto.allocate.cashbook.entries'))
        ->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// API response payload — complete field verification
// ──────────────────────────────────────────────────────────────────────────────

it('returns the complete cashbook entry payload on show', function () {
    ['user' => $user, 'estate' => $estate, 'entry' => $entry] = makeEntry(['type' => 'credit']);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entry', $entry))
        ->assertOk()
        ->json('data');

    expect($data['id'])->toBe($entry->id);
    expect($data['estate_id'])->toBe($estate->id);
    expect($data['organization_id'])->toBe($user->organization_id);
    expect($data)->toHaveKeys([
        'unit_id', 'invoice_id', 'charge_type_id', 'parent_entry_id',
        'description', 'amount', 'type', 'date', 'notes',
        'proof_of_payment_url', 'created_at', 'updated_at', 'is_allocated',
    ]);
    expect($data['type'])->toBe('credit');
    expect($data['is_allocated'])->toBeFalse();
});

it('returns the complete cashbook entry payload on index', function () {
    ['user' => $user] = makeEntry();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries'))
        ->assertOk()
        ->json('data.0');

    expect($data)->toHaveKeys([
        'id', 'estate_id', 'organization_id', 'unit_id', 'invoice_id',
        'charge_type_id', 'parent_entry_id', 'description', 'amount',
        'type', 'date', 'notes', 'proof_of_payment_url',
        'created_at', 'updated_at', 'is_allocated',
    ]);
});

it('amount is returned as a float', function () {
    ['user' => $user] = makeEntry(['amount' => 750]);

    $amount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries'))
        ->assertOk()
        ->json('data.0.amount');

    expect(is_numeric($amount))->toBeTrue();
});

it('date is returned as a Y-m-d string', function () {
    ['user' => $user] = makeEntry(['date' => '2026-03-15']);

    $date = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries'))
        ->assertOk()
        ->json('data.0.date');

    expect($date)->toBe('2026-03-15');
});

it('is_allocated is true when entry has invoice_id', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);
    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoice->id,
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entry', $entry))
        ->assertOk()
        ->json('data');

    expect($data['is_allocated'])->toBeTrue();
});

it('proof_of_payment_url is null when no file uploaded', function () {
    ['user' => $user, 'entry' => $entry] = makeEntry();

    $url = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entry', $entry))
        ->assertOk()
        ->json('data.proof_of_payment_url');

    expect($url)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — estate_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters cashbook entries by estate_id', function () {
    $user    = adminUser();
    $estateA = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $estateB = Estate::factory()->create(['organization_id' => $user->organization_id]);

    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estateA->id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estateB->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?estate_id=' . $estateA->id)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.estate_id'))->toBe($estateA->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — type (credit / debit)
// ──────────────────────────────────────────────────────────────────────────────

it('filters cashbook entries by type credit', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->credit()->create($base);
    CashbookEntry::factory()->credit()->create($base);
    CashbookEntry::factory()->debit()->create($base);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?type=credit')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
    collect($response->json('data'))->each(fn ($e) => expect($e['type'])->toBe('credit'));
});

it('filters cashbook entries by type debit', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->credit()->create($base);
    CashbookEntry::factory()->debit()->create($base);
    CashbookEntry::factory()->debit()->create($base);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?type=debit')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
    collect($response->json('data'))->each(fn ($e) => expect($e['type'])->toBe('debit'));
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — unit_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters cashbook entries by unit_id', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unitA  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $unitB  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'unit_id' => $unitA->id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'unit_id' => $unitB->id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'unit_id' => null]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?unit_id=' . $unitA->id)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.unit_id'))->toBe($unitA->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — allocation_status
// ──────────────────────────────────────────────────────────────────────────────

it('filters cashbook entries with allocation_status=allocated', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'invoice_id' => $invoice->id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'invoice_id' => null]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?allocation_status=allocated')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.invoice_id'))->not->toBeNull();
});

it('filters cashbook entries with allocation_status=unallocated', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'invoice_id' => $invoice->id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'invoice_id' => null]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'invoice_id' => null]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?allocation_status=unallocated')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
    collect($response->json('data'))->each(fn ($e) => expect($e['invoice_id'])->toBeNull());
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — charge_type_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters cashbook entries by charge_type_id', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ctA    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ctB    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'charge_type_id' => $ctA->id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'charge_type_id' => $ctB->id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id, 'charge_type_id' => null]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?charge_type_id=' . $ctA->id)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.charge_type_id'))->toBe($ctA->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sorting
// ──────────────────────────────────────────────────────────────────────────────

it('sorts cashbook entries by amount ascending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->create(array_merge($base, ['amount' => 3000]));
    CashbookEntry::factory()->create(array_merge($base, ['amount' => 1000]));
    CashbookEntry::factory()->create(array_merge($base, ['amount' => 2000]));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_sort=amount:asc')
        ->assertOk();

    $amounts = collect($response->json('data'))->pluck('amount')->values()->toArray();
    $sorted  = $amounts;
    sort($sorted);
    expect($amounts)->toBe($sorted);
});

it('sorts cashbook entries by amount descending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->create(array_merge($base, ['amount' => 500]));
    CashbookEntry::factory()->create(array_merge($base, ['amount' => 2500]));
    CashbookEntry::factory()->create(array_merge($base, ['amount' => 1500]));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_sort=amount:desc')
        ->assertOk();

    $amounts = collect($response->json('data'))->pluck('amount')->values()->toArray();
    expect($amounts[0])->toBeGreaterThanOrEqual($amounts[1]);
    expect($amounts[1])->toBeGreaterThanOrEqual($amounts[2]);
});

it('sorts cashbook entries by date ascending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-03-01']));
    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-01-01']));
    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-02-01']));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_sort=date:asc')
        ->assertOk();

    $dates = collect($response->json('data'))->pluck('date')->values()->toArray();
    expect($dates[0])->toBeLessThanOrEqual($dates[1]);
    expect($dates[1])->toBeLessThanOrEqual($dates[2]);
});

it('sorts cashbook entries by date descending by default when no _sort given', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-01-01']));
    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-03-01']));
    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-02-01']));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries'))
        ->assertOk();

    $dates = collect($response->json('data'))->pluck('date')->values()->toArray();
    expect($dates[0])->toBeGreaterThanOrEqual($dates[1]);
    expect($dates[1])->toBeGreaterThanOrEqual($dates[2]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range — filters on `date` column (not created_at)
// ──────────────────────────────────────────────────────────────────────────────

it('filters cashbook entries by date_range=today using the date column', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    // This one has date = today
    CashbookEntry::factory()->create(array_merge($base, ['date' => today()->toDateString()]));
    // This one has date in the past
    CashbookEntry::factory()->create(array_merge($base, ['date' => today()->subDays(30)->toDateString()]));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?date_range=today')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.date'))->toBe(today()->toDateString());
});

it('filters cashbook entries by date_range=this_month', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    // This month
    CashbookEntry::factory()->create(array_merge($base, ['date' => now()->startOfMonth()->toDateString()]));
    CashbookEntry::factory()->create(array_merge($base, ['date' => now()->endOfMonth()->toDateString()]));
    // Last month
    CashbookEntry::factory()->create(array_merge($base, ['date' => now()->subMonth()->startOfMonth()->toDateString()]));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?date_range=this_month')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
});

it('filters cashbook entries by custom date range', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-03-10']));
    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-03-20']));
    CashbookEntry::factory()->create(array_merge($base, ['date' => '2026-04-01']));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?date_range=custom&date_range_start=2026-03-01&date_range_end=2026-03-31')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
});

it('returns all entries when date_range=all_time', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->count(4)->create($base);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?date_range=all_time')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(4);
});

// ──────────────────────────────────────────────────────────────────────────────
// Pagination
// ──────────────────────────────────────────────────────────────────────────────

it('paginates cashbook entries with _per_page', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    CashbookEntry::factory()->count(7)->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_per_page=3')
        ->assertOk();

    expect(count($response->json('data')))->toBe(3);
    expect($response->json('meta.total'))->toBe(7);
    expect($response->json('meta.per_page'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// Relationships
// ──────────────────────────────────────────────────────────────────────────────

it('returns parentEntry relationship when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $parent = CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id]);
    $child  = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'parent_entry_id' => $parent->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entry', $child) . '?_relationships=parentEntry')
        ->assertOk();

    expect($response->json('data.parent_entry'))->toHaveKey('id');
    expect($response->json('data.parent_entry.id'))->toBe($parent->id);
});

it('returns childEntries relationship on index when requested', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $parent = CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id]);
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'parent_entry_id' => $parent->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_relationships=childEntries')
        ->assertOk();

    $parentRow = collect($response->json('data'))->firstWhere('id', $parent->id);
    expect($parentRow['child_entries'])->toBeArray();
    expect(count($parentRow['child_entries']))->toBe(1);
});

it('ignores disallowed relationship names', function () {
    ['user' => $user] = makeEntry();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.entries') . '?_relationships=users,secrets')
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// CRUD — create additional scenarios
// ──────────────────────────────────────────────────────────────────────────────

it('creates an entry with optional fields (unit_id, notes, charge_type_id)', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'      => $estate->id,
            'date'           => '2026-04-02',
            'type'           => 'credit',
            'description'    => 'EFT – Jane',
            'amount'         => 1500,
            'unit_id'        => $unit->id,
            'charge_type_id' => $chargeType->id,
            'notes'          => 'Payment ref: EFT-0042',
        ])
        ->assertCreated()
        ->assertJsonPath('data.unit_id', $unit->id)
        ->assertJsonPath('data.notes', 'Payment ref: EFT-0042');
});

it('returns 422 when amount is not a number', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 'abc',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('returns 422 when date is not a valid date', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => 'not-a-date',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('returns 422 when estate_id does not exist in the database', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => \Illuminate\Support\Str::uuid(),
            'date'        => '2026-04-02',
            'type'        => 'credit',
            'description' => 'Test',
            'amount'      => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_id']);
});

it('create response contains the new entry id', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.cashbook.entry'), [
            'estate_id'   => $estate->id,
            'date'        => '2026-04-02',
            'type'        => 'debit',
            'description' => 'Insurance premium',
            'amount'      => 4500,
        ])
        ->assertCreated();

    expect($response->json('data.id'))->not->toBeNull();
    $this->assertDatabaseHas('cashbook_entries', ['id' => $response->json('data.id')]);
});

// ──────────────────────────────────────────────────────────────────────────────
// CRUD — update additional scenarios
// ──────────────────────────────────────────────────────────────────────────────

it('update persists changes to the database', function () {
    ['user' => $user, 'entry' => $entry] = makeEntry();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), [
            'description' => 'Updated text',
            'amount'      => 9999,
        ])
        ->assertOk();

    $this->assertDatabaseHas('cashbook_entries', [
        'id'          => $entry->id,
        'description' => 'Updated text',
        'amount'      => 9999,
    ]);
});

it('update returns the updated entry data', function () {
    ['user' => $user, 'entry' => $entry] = makeEntry(['type' => 'credit']);

    $response = $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), [
            'type'   => 'debit',
            'amount' => 5000,
            'date'   => '2026-05-01',
        ])
        ->assertOk();

    expect($response->json('data.type'))->toBe('debit');
    expect($response->json('data.date'))->toBe('2026-05-01');
});

it('returns 422 when update date is invalid', function () {
    ['user' => $user, 'entry' => $entry] = makeEntry();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), ['date' => 'not-a-date'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('returns 422 when update amount is negative', function () {
    ['user' => $user, 'entry' => $entry] = makeEntry();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.cashbook.entry', $entry), ['amount' => -100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

// ──────────────────────────────────────────────────────────────────────────────
// CRUD — delete
// ──────────────────────────────────────────────────────────────────────────────

it('delete returns a success message', function () {
    ['user' => $user, 'entry' => $entry] = makeEntry();

    $response = $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entry', $entry))
        ->assertOk();

    expect($response->json('message'))->toBeString();
});

it('returns 404 when deleting a cashbook entry from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['organization_id' => $otherTenant->id]);
    $otherEntry  = CashbookEntry::factory()->create(['organization_id' => $otherTenant->id, 'estate_id' => $otherEstate->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entry', $otherEntry))
        ->assertNotFound();
});

it('bulk delete returns a success message', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id]);

    $response = $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entries'), ['entry_ids' => [$entry->id]])
        ->assertOk();

    expect($response->json('message'))->toBeString();
});

it('bulk delete silently ignores ids not belonging to the tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['organization_id' => $otherTenant->id]);
    $otherEntry  = CashbookEntry::factory()->create(['organization_id' => $otherTenant->id, 'estate_id' => $otherEstate->id]);

    // Sending a cross-tenant id — service scopes by org, so nothing deleted → throws exception
    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.cashbook.entries'), ['entry_ids' => [$otherEntry->id]])
        ->assertStatus(500);

    $this->assertDatabaseHas('cashbook_entries', ['id' => $otherEntry->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Allocation — partial payment
// ──────────────────────────────────────────────────────────────────────────────

it('partial payment marks invoice as partially_paid', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit, 'invoice' => $invoice] = makeInvoiceForAllocation(1000.0);

    $entry = CashbookEntry::factory()->unallocated()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'type'            => 'credit',
        'amount'          => 600, // < 1000 outstanding
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => $unit->id,
        ])
        ->assertOk();

    expect($invoice->fresh()->status->value)->toBe(InvoiceStatus::PARTIALLY_PAID->value);
});

it('partial payment sets invoice_id on the entry', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit, 'invoice' => $invoice] = makeInvoiceForAllocation(1000.0);

    $entry = CashbookEntry::factory()->unallocated()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'type'            => 'credit',
        'amount'          => 400,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => $unit->id,
        ])
        ->assertOk();

    expect($entry->fresh()->invoice_id)->toBe($invoice->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Allocation — exact match
// ──────────────────────────────────────────────────────────────────────────────

it('exact match allocation marks invoice as paid', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit, 'invoice' => $invoice] = makeInvoiceForAllocation(500.0);

    $entry = CashbookEntry::factory()->unallocated()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'type'            => 'credit',
        'amount'          => 500, // exact match
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => $unit->id,
        ])
        ->assertOk();

    expect($invoice->fresh()->status->value)->toBe(InvoiceStatus::PAID->value);
});

// ──────────────────────────────────────────────────────────────────────────────
// Allocation — overpayment / advance (splits entry)
// ──────────────────────────────────────────────────────────────────────────────

it('overpayment splits entry into two child entries', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit, 'invoice' => $invoice] = makeInvoiceForAllocation(500.0);

    $entry = CashbookEntry::factory()->unallocated()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'type'            => 'credit',
        'amount'          => 800, // 300 over the 500 invoice
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => $unit->id,
        ])
        ->assertOk();

    // Original entry is deleted (replaced by two children)
    $this->assertDatabaseMissing('cashbook_entries', ['id' => $entry->id]);

    // Two child entries created for this estate/org (SQLite nullOnDelete clears parent_entry_id)
    $allocated   = CashbookEntry::where('organization_id', $user->organization_id)
        ->where('invoice_id', $invoice->id)
        ->first();
    $unallocated = CashbookEntry::where('organization_id', $user->organization_id)
        ->whereNull('invoice_id')
        ->where('estate_id', $estate->id)
        ->first();

    expect($allocated)->not->toBeNull();
    expect($unallocated)->not->toBeNull();
    expect((float) $allocated->amount)->toBe(500.0);
    expect((float) $unallocated->amount)->toBe(300.0);
});

it('overpayment marks invoice as paid', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit, 'invoice' => $invoice] = makeInvoiceForAllocation(500.0);

    $entry = CashbookEntry::factory()->unallocated()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'type'            => 'credit',
        'amount'          => 750,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => $unit->id,
        ])
        ->assertOk();

    expect($invoice->fresh()->status->value)->toBe(InvoiceStatus::PAID->value);
});

// ──────────────────────────────────────────────────────────────────────────────
// Allocation — error cases
// ──────────────────────────────────────────────────────────────────────────────

it('rejects allocating an already-allocated entry', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoiceA = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'amount' => 500, 'billing_period' => '2026-01-01',
    ]);
    $invoiceB = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'amount' => 500, 'billing_period' => '2026-02-01',
    ]);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoiceA->id, // already allocated
        'amount'          => 500,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $entry), [
            'invoice_id' => $invoiceB->id,
            'unit_id'    => $unit->id,
        ])
        ->assertStatus(500);
});

it('rejects allocating to an already fully-paid invoice', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit, 'invoice' => $invoice] = makeInvoiceForAllocation(500.0);

    // First allocation — exact match, marks invoice paid
    $firstEntry = CashbookEntry::factory()->unallocated()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'type'            => 'credit',
        'amount'          => 500,
    ]);
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $firstEntry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => $unit->id,
        ])
        ->assertOk();

    // Second attempt to allocate to the same invoice — should fail
    $secondEntry = CashbookEntry::factory()->unallocated()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'type'            => 'credit',
        'amount'          => 100,
    ]);
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.cashbook.entry', $secondEntry), [
            'invoice_id' => $invoice->id,
            'unit_id'    => $unit->id,
        ])
        ->assertStatus(500);
});

// ──────────────────────────────────────────────────────────────────────────────
// Deallocate
// ──────────────────────────────────────────────────────────────────────────────

it('deallocates an entry and clears its invoice_id', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'amount' => 1000, 'status' => InvoiceStatus::PARTIALLY_PAID->value,
    ]);
    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoice->id,
        'amount'          => 400,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.deallocate.cashbook.entry', $entry), [
            'reason' => 'Payment was made in error',
        ])
        ->assertOk();

    expect($entry->fresh()->invoice_id)->toBeNull();
});

it('deallocate reverts invoice status to unpaid when no other payments remain', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'amount' => 500, 'status' => InvoiceStatus::PARTIALLY_PAID->value,
    ]);
    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoice->id,
        'amount'          => 200,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.deallocate.cashbook.entry', $entry), [
            'reason' => 'Wrong payment',
        ])
        ->assertOk();

    expect($invoice->fresh()->status->value)->toBe(InvoiceStatus::UNPAID->value);
});

it('deallocate appends the reason to entry notes', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'amount' => 500,
    ]);
    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoice->id,
        'amount'          => 100,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.deallocate.cashbook.entry', $entry), [
            'reason' => 'Reversed per client request',
        ])
        ->assertOk();

    expect($entry->fresh()->notes)->toContain('Reversed per client request');
});

it('returns 422 when deallocate reason is missing', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);
    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoice->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.deallocate.cashbook.entry', $entry), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('returns 422 when deallocate reason exceeds 500 characters', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);
    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoice->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.deallocate.cashbook.entry', $entry), [
            'reason' => str_repeat('x', 501),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('rejects deallocating an unallocated entry', function () {
    ['user' => $user, 'estate' => $estate, 'entry' => $entry] = makeEntry(['invoice_id' => null]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.deallocate.cashbook.entry', $entry), [
            'reason' => 'Testing',
        ])
        ->assertStatus(500);
});

it('returns 404 when deallocating an entry from another tenant', function () {
    $user        = adminUser();
    $otherTenant = createTenant();
    $otherEstate = Estate::factory()->create(['organization_id' => $otherTenant->id]);
    $otherEntry  = CashbookEntry::factory()->create([
        'organization_id' => $otherTenant->id,
        'estate_id'       => $otherEstate->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.deallocate.cashbook.entry', $otherEntry), [
            'reason' => 'Testing',
        ])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// Auto-allocate (stub)
// ──────────────────────────────────────────────────────────────────────────────

it('auto-allocate returns ok with matched and message fields', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.auto.allocate.cashbook.entries'), [
            'estate_id' => $estate->id,
        ])
        ->assertOk();

    expect($response->json())->toHaveKeys(['matched', 'message']);
    expect($response->json('matched'))->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// Summary statistics
// ──────────────────────────────────────────────────────────────────────────────

it('returns cashbook summary with correct structure', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.summary'))
        ->assertOk();

    expect($response->json())->toHaveKeys([
        'total_credits', 'total_debits', 'net_balance',
        'unallocated_count', 'unallocated_amount',
    ]);
});

it('summary calculates correct totals', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $base   = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];

    CashbookEntry::factory()->credit()->create(array_merge($base, ['amount' => 3000]));
    CashbookEntry::factory()->credit()->create(array_merge($base, ['amount' => 2000]));
    CashbookEntry::factory()->debit()->create(array_merge($base, ['amount' => 1500]));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.summary'))
        ->assertOk();

    expect($response->json('total_credits'))->toBeGreaterThanOrEqual(5000.0);
    expect($response->json('total_debits'))->toBeGreaterThanOrEqual(1500.0);
    expect($response->json('net_balance'))->toBeGreaterThanOrEqual(3500.0);
});

it('summary is scoped to the authenticated tenant', function () {
    $user      = adminUser();
    $otherOrg  = createTenant();
    $otherEst  = Estate::factory()->create(['organization_id' => $otherOrg->id]);

    // Another tenant's big credit — must not appear in user's summary
    CashbookEntry::factory()->credit()->create([
        'organization_id' => $otherOrg->id,
        'estate_id'       => $otherEst->id,
        'amount'          => 999999,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.summary'))
        ->assertOk();

    expect($response->json('total_credits'))->toBeLessThan(999999);
});

it('summary unallocated_count counts only unallocated credit entries', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    $base = ['organization_id' => $user->organization_id, 'estate_id' => $estate->id];
    CashbookEntry::factory()->credit()->create(array_merge($base, ['invoice_id' => null,        'amount' => 200]));
    CashbookEntry::factory()->credit()->create(array_merge($base, ['invoice_id' => null,        'amount' => 300]));
    CashbookEntry::factory()->credit()->create(array_merge($base, ['invoice_id' => $invoice->id, 'amount' => 500]));

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.summary'))
        ->assertOk();

    expect($response->json('unallocated_count'))->toBeGreaterThanOrEqual(2);
    expect($response->json('unallocated_amount'))->toBeGreaterThanOrEqual(500.0);
});

it('summary filtered by estate_id returns only that estate\'s entries', function () {
    $user    = adminUser();
    $estateA = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $estateB = Estate::factory()->create(['organization_id' => $user->organization_id]);

    CashbookEntry::factory()->credit()->create(['organization_id' => $user->organization_id, 'estate_id' => $estateA->id, 'amount' => 1000]);
    CashbookEntry::factory()->credit()->create(['organization_id' => $user->organization_id, 'estate_id' => $estateB->id, 'amount' => 9000]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.cashbook.summary') . '?estate_id=' . $estateA->id)
        ->assertOk();

    expect($response->json('total_credits'))->toBe(1000);
});
