<?php

use App\Enums\InvoiceStatus;
use App\Models\CashbookEntry;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a unit that is "in arrears" — it has at least one overdue invoice.
 * Returns ['user', 'estate', 'unit', 'owner', 'chargeType', 'invoice'].
 */
function makeOverdueUnit(float $amount = 1000.0, array $unitOverrides = [], array $invoiceOverrides = []): array
{
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(array_merge([
        'estate_id'       => $estate->id,
        'organization_id' => $user->organization_id,
    ], $unitOverrides));
    $owner   = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->overdue()->create(array_merge([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'amount'          => $amount,
        'due_date'        => now()->subDays(10)->format('Y-m-d'),
    ], $invoiceOverrides));

    return compact('user', 'estate', 'unit', 'owner', 'chargeType', 'invoice');
}

// ──────────────────────────────────────────────────────────────────────────────
// Authentication
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 when unauthenticated', function () {
    $this->getJson(route('api.v1.show.arrears'))
        ->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// Response structure
// ──────────────────────────────────────────────────────────────────────────────

it('returns all top-level keys in the response', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk();

    expect($response->json())->toHaveKeys(['data', 'summary', 'estates', 'charge_types', 'meta']);
});

it('returns all summary keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk();

    expect($response->json('summary'))->toHaveKeys([
        'units_in_arrears', 'total_units', 'total_overdue_amount', 'total_overdue_invoices',
        'arrears_rate', 'estates_affected',
        'by_estate', 'by_duration', 'by_estate_type', 'by_charge_type',
        'top_owner_arrears', 'top_tenant_arrears',
    ]);
});

it('returns all by_duration bucket keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk();

    expect($response->json('summary.by_duration'))->toHaveKeys(['under_30', 'd30_60', 'd60_90', 'd90_plus']);
});

it('returns all meta pagination keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk();

    expect($response->json('meta'))->toHaveKeys(['total', 'current_page', 'last_page', 'per_page']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Unit payload fields
// ──────────────────────────────────────────────────────────────────────────────

it('each unit in data has overdue_amount, overdue_count and oldest_overdue_date', function () {
    ['user' => $user] = makeOverdueUnit(750.0);

    $unit = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0');

    expect($unit)->toHaveKeys(['id', 'overdue_amount', 'overdue_count', 'oldest_overdue_date']);
});

it('overdue_amount is returned as a numeric value', function () {
    ['user' => $user] = makeOverdueUnit(500.0);

    $amount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0.overdue_amount');

    expect(is_numeric($amount))->toBeTrue();
});

it('overdue_count is returned as an integer', function () {
    ['user' => $user] = makeOverdueUnit();

    $count = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0.overdue_count');

    expect($count)->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// Business rule: only overdue invoices trigger arrears
// ──────────────────────────────────────────────────────────────────────────────

it('includes units with overdue invoices in data', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnit();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('excludes units whose only invoice is unpaid (not overdue)', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::UNPAID->value,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

it('excludes units whose only invoice is paid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::PAID->value,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

it('excludes units whose only invoice is partially paid', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::PARTIALLY_PAID->value,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

it('includes a unit that has both a paid invoice and an overdue invoice', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'status' => InvoiceStatus::PAID->value, 'billing_period' => '2026-01-01',
    ]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-02-01',
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Tenant isolation
// ──────────────────────────────────────────────────────────────────────────────

it('only returns units belonging to the authenticated tenant', function () {
    ['user' => $user, 'unit' => $myUnit]     = makeOverdueUnit();
    ['unit' => $otherUnit]                   = makeOverdueUnit(); // different org

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($myUnit->id);
    expect($ids)->not->toContain($otherUnit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Empty state
// ──────────────────────────────────────────────────────────────────────────────

it('returns empty data and zero summary when tenant has no overdue invoices', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
    expect($response->json('summary.units_in_arrears'))->toBe(0);
    expect((float) $response->json('summary.total_overdue_amount'))->toBe(0.0);
    expect($response->json('summary.total_overdue_invoices'))->toBe(0);
    expect($response->json('meta.total'))->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Summary statistics
// ──────────────────────────────────────────────────────────────────────────────

it('summary units_in_arrears counts only units with overdue invoices', function () {
    ['user' => $user] = makeOverdueUnit(); // 1 unit in arrears

    // A clean unit with no overdue invoice — should not count
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $unitsInArrears = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.units_in_arrears');

    expect($unitsInArrears)->toBe(1);
});

it('summary total_units counts all tenant units regardless of arrears status', function () {
    ['user' => $user] = makeOverdueUnit(); // unit 1

    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]); // unit 2 (no overdue)

    $totalUnits = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.total_units');

    expect($totalUnits)->toBe(2);
});

it('summary total_overdue_amount reflects sum of all overdue invoice amounts', function () {
    $user    = adminUser();
    $estate  = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct      = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([2000, 3000] as $i => $amt) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'amount' => $amt, 'billing_period' => '2026-0' . ($i + 1) . '-01',
        ]);
    }

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.total_overdue_amount');

    expect((float) $total)->toBe(5000.0);
});

it('summary total_overdue_invoices counts all overdue invoices', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    // 3 overdue invoices for one unit
    foreach (['2026-01-01', '2026-02-01', '2026-03-01'] as $period) {
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => $period,
        ]);
    }

    $count = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.total_overdue_invoices');

    expect($count)->toBe(3);
});

it('summary arrears_rate is calculated correctly', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    // 1 unit in arrears, 1 unit clean → rate = 50%
    $unitA = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner = Owner::factory()->create(['unit_id' => $unitA->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitA->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]); // no invoice

    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.arrears_rate');

    expect((float) $rate)->toBe(50.0);
});

it('summary estates_affected counts distinct estates with overdue units', function () {
    $user = adminUser();

    // Two separate estates, each with one overdue unit
    foreach (range(1, 2) as $_) {
        $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
        $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
        $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
        $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        ]);
    }

    $estatesAffected = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.estates_affected');

    expect($estatesAffected)->toBe(2);
});

it('summary arrears_rate is 0 when no units exist', function () {
    $user = adminUser();

    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.arrears_rate');

    expect($rate)->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// overdue_amount calculation
// ──────────────────────────────────────────────────────────────────────────────

it('overdue_amount equals invoice amount when there are no payments', function () {
    ['user' => $user, 'invoice' => $invoice] = makeOverdueUnit(1200.0);

    $overdueAmount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0.overdue_amount');

    expect((float) $overdueAmount)->toBe(1200.0);
});

it('overdue_amount is reduced by partial cashbook payments', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit, 'invoice' => $invoice] = makeOverdueUnit(1000.0);

    // Partial payment of 300 allocated to this invoice
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoice->id,
        'type'            => 'credit',
        'amount'          => 300,
    ]);

    $overdueAmount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0.overdue_amount');

    expect((float) $overdueAmount)->toBe(700.0);
});

it('overdue_amount does not go below zero even if payments exceed invoice amount', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit, 'invoice' => $invoice] = makeOverdueUnit(500.0);

    // Overpayment — 700 credited against a 500 invoice
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'invoice_id'      => $invoice->id,
        'type'            => 'credit',
        'amount'          => 700,
    ]);

    $overdueAmount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0.overdue_amount');

    expect((float) $overdueAmount)->toBe(0.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// overdue_count per unit
// ──────────────────────────────────────────────────────────────────────────────

it('overdue_count equals the number of overdue invoices on a unit', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    foreach (['2026-01-01', '2026-02-01', '2026-03-01'] as $period) {
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => $period,
        ]);
    }

    $overdueCount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0.overdue_count');

    expect($overdueCount)->toBe(3);
});

it('overdue_count excludes paid invoices on the same unit', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-01-01',
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'status' => InvoiceStatus::PAID->value, 'billing_period' => '2026-02-01',
    ]);

    $overdueCount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0.overdue_count');

    expect($overdueCount)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// oldest_overdue_date
// ──────────────────────────────────────────────────────────────────────────────

it('oldest_overdue_date is the earliest due_date across all overdue invoices on that unit', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'due_date' => '2026-03-15', 'billing_period' => '2026-02-01',
    ]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'due_date' => '2026-01-20', 'billing_period' => '2026-01-01', // earliest
    ]);

    $oldestDate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.0.oldest_overdue_date');

    expect(substr($oldestDate, 0, 10))->toBe('2026-01-20');
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.by_estate
// ──────────────────────────────────────────────────────────────────────────────

it('by_estate contains an entry for each estate with overdue units', function () {
    ['user' => $user, 'estate' => $estate] = makeOverdueUnit();

    $byEstate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.by_estate');

    $estateIds = array_column($byEstate, 'id');
    expect($estateIds)->toContain($estate->id);
});

it('by_estate entry has correct structure', function () {
    ['user' => $user] = makeOverdueUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.by_estate.0');

    expect($entry)->toHaveKeys(['id', 'name', 'type', 'units_count', 'overdue_total']);
});

it('by_estate units_count reflects number of overdue units in that estate', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    // 2 units in the same estate, both with overdue invoices
    foreach (['A101', 'A102'] as $num) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        ]);
    }

    $byEstate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.by_estate');

    $row = collect($byEstate)->firstWhere('id', $estate->id);
    expect($row['units_count'])->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.by_charge_type
// ──────────────────────────────────────────────────────────────────────────────

it('by_charge_type contains the charge type name as a key', function () {
    ['user' => $user, 'chargeType' => $ct] = makeOverdueUnit(1500.0);

    $byChargeType = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.by_charge_type');

    expect(array_key_exists($ct->name, $byChargeType))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.top_owner_arrears
// ──────────────────────────────────────────────────────────────────────────────

it('top_owner_arrears includes the owner with the highest overdue amount', function () {
    ['user' => $user, 'owner' => $owner, 'unit' => $unit] = makeOverdueUnit(2000.0);

    $topOwners = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.top_owner_arrears');

    expect($topOwners)->not->toBeEmpty();
    $entry = $topOwners[0];
    expect($entry)->toHaveKeys(['unit_id', 'estate_id', 'name', 'unit_number', 'amount']);
    expect($entry['unit_id'])->toBe($unit->id);
    expect($entry['name'])->toBe($owner->full_name);
    expect((float) $entry['amount'])->toBe(2000.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.top_tenant_arrears
// ──────────────────────────────────────────────────────────────────────────────

it('top_tenant_arrears includes active tenants on units with overdue invoices', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnit(1800.0);

    $tenant = Tenant::factory()->create([
        'unit_id'         => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active'       => true,
    ]);

    $topTenants = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.top_tenant_arrears');

    expect($topTenants)->not->toBeEmpty();
    $entry = $topTenants[0];
    expect($entry)->toHaveKeys(['unit_id', 'estate_id', 'name', 'unit_number', 'amount']);
    expect($entry['name'])->toBe($tenant->full_name);
});

it('top_tenant_arrears excludes inactive tenants', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnit(1800.0);

    Tenant::factory()->create([
        'unit_id'         => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active'       => false, // inactive — should not appear
    ]);

    $topTenants = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.top_tenant_arrears');

    expect($topTenants)->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — estate_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters data to a specific estate_id', function () {
    $user = adminUser();

    ['estate' => $estateA, 'unit' => $unitA] = makeOverdueUnit(1000.0, ['organization_id' => $user->organization_id]);

    // Second estate + overdue unit under the same user
    $estateB = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct      = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unitB   = Unit::factory()->create(['estate_id' => $estateB->id, 'organization_id' => $user->organization_id]);
    $ownerB  = Owner::factory()->create(['unit_id' => $unitB->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitB->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerB->id,
    ]);

    // Rebuild helpers from user A's estate A
    $userA  = $estateA->where('organization_id', $user->organization_id)->first() ? $user : $user;

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?estate_id=' . $estateA->id)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unitA->id);
    expect($ids)->not->toContain($unitB->id);
});

it('filters data to a specific estate_id — only that estate units returned', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit] = makeOverdueUnit();

    // Add a second overdue unit in a different estate under same user
    $estate2 = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct2     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $unit2   = Unit::factory()->create(['estate_id' => $estate2->id, 'organization_id' => $user->organization_id]);
    $owner2  = Owner::factory()->create(['unit_id' => $unit2->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit2->id,
        'charge_type_id' => $ct2->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner2->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?estate_id=' . $estate->id)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
    expect($ids)->not->toContain($unit2->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — estate_type
// ──────────────────────────────────────────────────────────────────────────────

it('filters data by estate_type', function () {
    $user    = adminUser();
    $estateA = Estate::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    $estateB = Estate::factory()->residentialRental()->create(['organization_id' => $user->organization_id]);
    $ct      = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([$estateA, $estateB] as $estate) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        ]);
    }

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?estate_type=sectional_title')
        ->assertOk()
        ->json('data');

    expect(count($data))->toBe(1);
    // Verify the returned unit belongs to the sectional_title estate
    expect($data[0]['estate_id'])->toBe($estateA->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — charge_type code
// ──────────────────────────────────────────────────────────────────────────────

it('filters data by charge_type code', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $levyCt = ChargeType::factory()->create(['organization_id' => $user->organization_id, 'type' => 'admin_levy']);
    $rentCt = ChargeType::factory()->create(['organization_id' => $user->organization_id, 'type' => 'rent']);

    $unitLevy = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => 'L1']);
    $ownerL   = Owner::factory()->create(['unit_id' => $unitLevy->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitLevy->id,
        'charge_type_id' => $levyCt->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerL->id,
    ]);

    $unitRent = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => 'R1']);
    $ownerR   = Owner::factory()->create(['unit_id' => $unitRent->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitRent->id,
        'charge_type_id' => $rentCt->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerR->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?charge_type=' . $levyCt->id)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unitLevy->id);
    expect($ids)->not->toContain($unitRent->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (ilike — SQLite-incompatible, skipped)
// ──────────────────────────────────────────────────────────────────────────────

it('searches arrears by unit_number', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnit(1000.0, ['unit_number' => 'XYZ-99']);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_search=XYZ-99')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('searches arrears by owner full_name', function () {
    ['user' => $user, 'owner' => $owner, 'unit' => $unit] = makeOverdueUnit();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_search=' . urlencode(substr($owner->full_name, 0, 5)))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('searches arrears by estate name', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit] = makeOverdueUnit();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_search=' . urlencode(substr($estate->name, 0, 5)))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('returns empty data when search term matches nothing', function () {
    makeOverdueUnit();
    $user = adminUser();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_search=ZZZNOMATCH999')
        ->assertOk()
        ->json('data');

    expect($data)->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Sorting
// ──────────────────────────────────────────────────────────────────────────────

it('sorts by overdue_amount descending by default (highest first)', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([['U1', 500], ['U2', 3000], ['U3', 1500]] as [$num, $amt]) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'amount' => $amt, 'billing_period' => '2026-01-01',
        ]);
    }

    $amounts = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('data.*.overdue_amount');

    expect((float) $amounts[0])->toBeGreaterThanOrEqual((float) $amounts[1]);
    expect((float) $amounts[1])->toBeGreaterThanOrEqual((float) $amounts[2]);
});

it('sorts by overdue_amount ascending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([['U1', 500], ['U2', 3000], ['U3', 1500]] as [$num, $amt]) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'amount' => $amt, 'billing_period' => '2026-01-01',
        ]);
    }

    $amounts = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_sort=overdue_amount:asc')
        ->assertOk()
        ->json('data.*.overdue_amount');

    expect((float) $amounts[0])->toBeLessThanOrEqual((float) $amounts[1]);
    expect((float) $amounts[1])->toBeLessThanOrEqual((float) $amounts[2]);
});

it('sorts by unit_number ascending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    foreach (['C3', 'A1', 'B2'] as $num) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => '2026-01-01',
        ]);
    }

    $numbers = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_sort=unit_number:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    $sorted = $numbers;
    sort($sorted);
    expect($numbers)->toBe($sorted);
});

it('sorts by unit_number descending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    foreach (['A1', 'C3', 'B2'] as $num) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => '2026-01-01',
        ]);
    }

    $numbers = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_sort=unit_number:desc')
        ->assertOk()
        ->json('data.*.unit_number');

    $sorted = $numbers;
    rsort($sorted);
    expect($numbers)->toBe($sorted);
});

it('sorts by oldest_overdue ascending (earliest due date first)', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([['U1', '2026-03-01'], ['U2', '2026-01-01'], ['U3', '2026-02-01']] as [$num, $due]) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'due_date' => $due, 'billing_period' => '2026-01-01',
        ]);
    }

    $dates = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_sort=oldest_overdue:asc')
        ->assertOk()
        ->json('data.*.oldest_overdue_date');

    expect($dates[0])->toBeLessThanOrEqual($dates[1]);
    expect($dates[1])->toBeLessThanOrEqual($dates[2]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range filtering (filters units.created_at)
// ──────────────────────────────────────────────────────────────────────────────

it('date range today returns units created today with overdue invoices', function () {
    ['user' => $user, 'unit' => $todayUnit] = makeOverdueUnit();

    // Unit created 2 months ago — should be excluded
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $oldUnit = Unit::factory()->create([
        'estate_id'       => $estate->id,
        'organization_id' => $user->organization_id,
        'created_at'      => now()->subMonths(2),
    ]);
    $oldOwner = Owner::factory()->create(['unit_id' => $oldUnit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $oldUnit->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $oldOwner->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_date_range=today')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($todayUnit->id);
    expect($ids)->not->toContain($oldUnit->id);
});

it('date range custom returns only units created in the specified window', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    $inRange = Unit::factory()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => '2026-02-15',
    ]);
    $inOwner = Owner::factory()->create(['unit_id' => $inRange->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $inRange->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $inOwner->id,
    ]);

    $outRange = Unit::factory()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => '2026-04-20',
    ]);
    $outOwner = Owner::factory()->create(['unit_id' => $outRange->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $outRange->id,
        'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $outOwner->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_date_range=custom&_date_range_start=2026-02-01&_date_range_end=2026-02-28')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($inRange->id);
    expect($ids)->not->toContain($outRange->id);
});

it('date range all_time returns all overdue units', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnit();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_date_range=all_time')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Pagination
// ──────────────────────────────────────────────────────────────────────────────

it('paginates results with _per_page', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    foreach (range(1, 5) as $i) {
        $unit  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => "U$i"]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'charge_type_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => '2026-01-01',
        ]);
    }

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears') . '?_per_page=2')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.per_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(5);
    expect($response->json('meta.last_page'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// Estates dropdown
// ──────────────────────────────────────────────────────────────────────────────

it('estates dropdown contains all tenant estates regardless of arrears status', function () {
    ['user' => $user, 'estate' => $estateWithArrears] = makeOverdueUnit();

    $emptyEstate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $estateIds = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('estates.*.id');

    expect($estateIds)->toContain($estateWithArrears->id);
    expect($estateIds)->toContain($emptyEstate->id);
});

it('estates dropdown does not include other tenants estates', function () {
    ['user' => $user] = makeOverdueUnit();

    $otherOrg   = createTenant();
    $otherEstate = Estate::factory()->create(['organization_id' => $otherOrg->id]);

    $estateIds = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('estates.*.id');

    expect($estateIds)->not->toContain($otherEstate->id);
});

it('estates dropdown entry has id, name and type fields', function () {
    ['user' => $user] = makeOverdueUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('estates.0');

    expect($entry)->toHaveKeys(['id', 'name', 'type']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Charge types dropdown
// ──────────────────────────────────────────────────────────────────────────────

it('charge_types dropdown only includes active charge types', function () {
    ['user' => $user] = makeOverdueUnit();

    $inactiveCt = ChargeType::factory()->inactive()->create(['organization_id' => $user->organization_id]);

    $codes = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('charge_types.*.id');

    expect($codes)->not->toContain($inactiveCt->id);
});

it('charge_types dropdown entry has id and name fields', function () {
    ['user' => $user] = makeOverdueUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('charge_types.0');

    expect($entry)->toHaveKeys(['id', 'name']);
});

it('charge_types dropdown does not include other tenants charge types', function () {
    ['user' => $user] = makeOverdueUnit();

    $otherOrg = createTenant();
    $otherCt  = ChargeType::factory()->create(['organization_id' => $otherOrg->id]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('charge_types.*.id');

    expect($ids)->not->toContain($otherCt->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.total_overdue_amount is tenant-scoped
// ──────────────────────────────────────────────────────────────────────────────

it('total_overdue_amount does not include other tenants overdue invoices', function () {
    ['user' => $user]     = makeOverdueUnit(500.0);  // own org
    makeOverdueUnit(99999.0);                         // different org — must not bleed in

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.arrears'))
        ->assertOk()
        ->json('summary.total_overdue_amount');

    expect((float) $total)->toBe(500.0);
});
