<?php

use App\Enums\InvoiceStatus;
use App\Models\CashbookEntry;
use App\Models\Ledger;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Occupant;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a unit that is "in arrears" — it has at least one overdue invoice.
 * Returns ['user', 'community', 'unit', 'owner', 'ledger', 'invoice'].
 */
function makeOverdueUnit(float $amount = 1000.0, array $unitOverrides = [], array $invoiceOverrides = []): array
{
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(array_merge([
        'community_id'       => $community->id,
        'organization_id' => $user->organization_id,
    ], $unitOverrides));
    $owner   = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice = Invoice::factory()->overdue()->create(array_merge([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ledger->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'amount'          => $amount,
        'due_date'        => now()->subDays(10)->format('Y-m-d'),
    ], $invoiceOverrides));

    return compact('user', 'community', 'unit', 'owner', 'ledger', 'invoice');
}

// ──────────────────────────────────────────────────────────────────────────────
// Authentication
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 when unauthenticated', function () {
    $this->getJson(route('api.v1.show.customers'))
        ->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// Response structure
// ──────────────────────────────────────────────────────────────────────────────

it('returns all top-level keys in the response', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk();

    expect($response->json())->toHaveKeys(['data', 'summary', 'communities', 'ledgers', 'meta']);
});

it('returns all summary keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk();

    expect($response->json('summary'))->toHaveKeys([
        'units_in_arrears', 'total_units', 'total_overdue_amount', 'total_overdue_invoices',
        'arrears_rate', 'communities_affected',
        'by_community', 'by_duration', 'by_community_type', 'by_ledger',
        'top_owner_arrears', 'top_occupant_arrears',
    ]);
});

it('returns all by_duration bucket keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk();

    expect($response->json('summary.by_duration'))->toHaveKeys(['under_30', 'd30_60', 'd60_90', 'd90_plus']);
});

it('returns all meta pagination keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk();

    expect($response->json('meta'))->toHaveKeys(['total', 'current_page', 'last_page', 'per_page']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Unit payload fields
// ──────────────────────────────────────────────────────────────────────────────

it('each unit in data has overdue_amount, overdue_count and oldest_overdue_date', function () {
    ['user' => $user] = makeOverdueUnit(750.0);

    $unit = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.0');

    expect($unit)->toHaveKeys(['id', 'overdue_amount', 'overdue_count', 'oldest_overdue_date']);
});

it('overdue_amount is returned as a numeric value', function () {
    ['user' => $user] = makeOverdueUnit(500.0);

    $amount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.0.overdue_amount');

    expect(is_numeric($amount))->toBeTrue();
});

it('overdue_count is returned as an integer', function () {
    ['user' => $user] = makeOverdueUnit();

    $count = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
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
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('excludes units whose only invoice is unpaid (not overdue)', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::UNPAID->value,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

it('excludes units whose only invoice is paid', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::PAID->value,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

it('excludes units whose only invoice is partially paid', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::PARTIALLY_PAID->value,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

it('includes a unit that has both a paid invoice and an overdue invoice', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'status' => InvoiceStatus::PAID->value, 'billing_period' => '2026-01-01',
    ]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-02-01',
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Occupant isolation
// ──────────────────────────────────────────────────────────────────────────────

it('only returns units belonging to the authenticated occupant', function () {
    ['user' => $user, 'unit' => $myUnit]     = makeOverdueUnit();
    ['unit' => $otherUnit]                   = makeOverdueUnit(); // different org

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($myUnit->id);
    expect($ids)->not->toContain($otherUnit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Empty state
// ──────────────────────────────────────────────────────────────────────────────

it('returns empty data and zero summary when occupant has no overdue invoices', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $unitsInArrears = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.units_in_arrears');

    expect($unitsInArrears)->toBe(1);
});

it('summary total_units counts all occupant units regardless of arrears status', function () {
    ['user' => $user] = makeOverdueUnit(); // unit 1

    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]); // unit 2 (no overdue)

    $totalUnits = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.total_units');

    expect($totalUnits)->toBe(2);
});

it('summary total_overdue_amount reflects sum of all overdue invoice amounts', function () {
    $user    = adminUser();
    $community  = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct      = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([2000, 3000] as $i => $amt) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'amount' => $amt, 'billing_period' => '2026-0' . ($i + 1) . '-01',
        ]);
    }

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.total_overdue_amount');

    expect((float) $total)->toBe(5000.0);
});

it('summary total_overdue_invoices counts all overdue invoices', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    // 3 overdue invoices for one unit
    foreach (['2026-01-01', '2026-02-01', '2026-03-01'] as $period) {
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => $period,
        ]);
    }

    $count = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.total_overdue_invoices');

    expect($count)->toBe(3);
});

it('summary arrears_rate is calculated correctly', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    // 1 unit in arrears, 1 unit clean → rate = 50%
    $unitA = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner = Owner::factory()->create(['unit_id' => $unitA->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitA->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
    ]);

    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]); // no invoice

    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.arrears_rate');

    expect((float) $rate)->toBe(50.0);
});

it('summary communities_affected counts distinct communities with overdue units', function () {
    $user = adminUser();

    // Two separate communities, each with one overdue unit
    foreach (range(1, 2) as $_) {
        $community = Community::factory()->create(['organization_id' => $user->organization_id]);
        $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
        $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
        $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        ]);
    }

    $communitiesAffected = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.communities_affected');

    expect($communitiesAffected)->toBe(2);
});

it('summary arrears_rate is 0 when no units exist', function () {
    $user = adminUser();

    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
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
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.0.overdue_amount');

    expect((float) $overdueAmount)->toBe(1200.0);
});

it('overdue_amount is reduced by partial cashbook payments', function () {
    ['user' => $user, 'community' => $community, 'unit' => $unit, 'invoice' => $invoice] = makeOverdueUnit(1000.0);

    // Partial payment of 300 allocated to this invoice
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'       => $community->id,
        'invoice_id'      => $invoice->id,
        'type'            => 'credit',
        'amount'          => 300,
    ]);

    $overdueAmount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.0.overdue_amount');

    expect((float) $overdueAmount)->toBe(700.0);
});

it('overdue_amount does not go below zero even if payments exceed invoice amount', function () {
    ['user' => $user, 'community' => $community, 'unit' => $unit, 'invoice' => $invoice] = makeOverdueUnit(500.0);

    // Overpayment — 700 credited against a 500 invoice
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'       => $community->id,
        'invoice_id'      => $invoice->id,
        'type'            => 'credit',
        'amount'          => 700,
    ]);

    $overdueAmount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.0.overdue_amount');

    expect((float) $overdueAmount)->toBe(0.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// overdue_count per unit
// ──────────────────────────────────────────────────────────────────────────────

it('overdue_count equals the number of overdue invoices on a unit', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    foreach (['2026-01-01', '2026-02-01', '2026-03-01'] as $period) {
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => $period,
        ]);
    }

    $overdueCount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.0.overdue_count');

    expect($overdueCount)->toBe(3);
});

it('overdue_count excludes paid invoices on the same unit', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'billing_period' => '2026-01-01',
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'status' => InvoiceStatus::PAID->value, 'billing_period' => '2026-02-01',
    ]);

    $overdueCount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.0.overdue_count');

    expect($overdueCount)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// oldest_overdue_date
// ──────────────────────────────────────────────────────────────────────────────

it('oldest_overdue_date is the earliest due_date across all overdue invoices on that unit', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'due_date' => '2026-03-15', 'billing_period' => '2026-02-01',
    ]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        'due_date' => '2026-01-20', 'billing_period' => '2026-01-01', // earliest
    ]);

    $oldestDate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.0.oldest_overdue_date');

    expect(substr($oldestDate, 0, 10))->toBe('2026-01-20');
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.by_community
// ──────────────────────────────────────────────────────────────────────────────

it('by_community contains an entry for each community with overdue units', function () {
    ['user' => $user, 'community' => $community] = makeOverdueUnit();

    $byCommunity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.by_community');

    $communityIds = array_column($byCommunity, 'id');
    expect($communityIds)->toContain($community->id);
});

it('by_community entry has correct structure', function () {
    ['user' => $user] = makeOverdueUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.by_community.0');

    expect($entry)->toHaveKeys(['id', 'name', 'entity_type', 'units_count', 'overdue_total']);
});

it('by_community units_count reflects number of overdue units in that community', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    // 2 units in the same community, both with overdue invoices
    foreach (['A101', 'A102'] as $num) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        ]);
    }

    $byCommunity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.by_community');

    $row = collect($byCommunity)->firstWhere('id', $community->id);
    expect($row['units_count'])->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.by_ledger
// ──────────────────────────────────────────────────────────────────────────────

it('by_ledger contains the ledger name as a key', function () {
    ['user' => $user, 'ledger' => $ct] = makeOverdueUnit(1500.0);

    $byLedger = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.by_ledger');

    expect(array_key_exists($ct->name, $byLedger))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.top_owner_arrears
// ──────────────────────────────────────────────────────────────────────────────

it('top_owner_arrears includes the owner with the highest overdue amount', function () {
    ['user' => $user, 'owner' => $owner, 'unit' => $unit] = makeOverdueUnit(2000.0);

    $topOwners = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.top_owner_arrears');

    expect($topOwners)->not->toBeEmpty();
    $entry = $topOwners[0];
    expect($entry)->toHaveKeys(['unit_id', 'community_id', 'name', 'unit_number', 'amount']);
    expect($entry['unit_id'])->toBe($unit->id);
    expect($entry['name'])->toBe($owner->full_name);
    expect((float) $entry['amount'])->toBe(2000.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.top_occupant_arrears
// ──────────────────────────────────────────────────────────────────────────────

it('top_occupant_arrears includes active occupants on units with overdue invoices', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnit(1800.0);

    $occupant = Occupant::factory()->create([
        'unit_id'         => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active'       => true,
    ]);

    $topOccupants = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.top_occupant_arrears');

    expect($topOccupants)->not->toBeEmpty();
    $entry = $topOccupants[0];
    expect($entry)->toHaveKeys(['unit_id', 'community_id', 'name', 'unit_number', 'amount']);
    expect($entry['name'])->toBe($occupant->full_name);
});

it('top_occupant_arrears excludes inactive occupants', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnit(1800.0);

    Occupant::factory()->create([
        'unit_id'         => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active'       => false, // inactive — should not appear
    ]);

    $topOccupants = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.top_occupant_arrears');

    expect($topOccupants)->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — community_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters data to a specific community_id', function () {
    $user = adminUser();

    ['community' => $communityA, 'unit' => $unitA] = makeOverdueUnit(1000.0, ['organization_id' => $user->organization_id]);

    // Second community + overdue unit under the same user
    $communityB = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct      = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unitB   = Unit::factory()->create(['community_id' => $communityB->id, 'organization_id' => $user->organization_id]);
    $ownerB  = Owner::factory()->create(['unit_id' => $unitB->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitB->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerB->id,
    ]);

    // Rebuild helpers from user A's community A
    $userA  = $communityA->where('organization_id', $user->organization_id)->first() ? $user : $user;

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?community_id=' . $communityA->id)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unitA->id);
    expect($ids)->not->toContain($unitB->id);
});

it('filters data to a specific community_id — only that community units returned', function () {
    ['user' => $user, 'community' => $community, 'unit' => $unit] = makeOverdueUnit();

    // Add a second overdue unit in a different community under same user
    $community2 = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct2     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $unit2   = Unit::factory()->create(['community_id' => $community2->id, 'organization_id' => $user->organization_id]);
    $owner2  = Owner::factory()->create(['unit_id' => $unit2->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit2->id,
        'ledger_id' => $ct2->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner2->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?community_id=' . $community->id)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
    expect($ids)->not->toContain($unit2->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — community_type
// ──────────────────────────────────────────────────────────────────────────────

it('filters data by community_type', function () {
    $user    = adminUser();
    $communityA = Community::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    $communityB = Community::factory()->residentialRental()->create(['organization_id' => $user->organization_id]);
    $ct      = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([$communityA, $communityB] as $community) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
        ]);
    }

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?community_type=body_corporate')
        ->assertOk()
        ->json('data');

    expect(count($data))->toBe(1);
    // Verify the returned unit belongs to the sectional_title community
    expect($data[0]['community_id'])->toBe($communityA->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — ledger code
// ──────────────────────────────────────────────────────────────────────────────

it('filters data by ledger code', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $levyCt = Ledger::factory()->create(['organization_id' => $user->organization_id, 'type' => 'admin_levy']);
    $rentCt = Ledger::factory()->create(['organization_id' => $user->organization_id, 'type' => 'rent']);

    $unitLevy = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => 'L1']);
    $ownerL   = Owner::factory()->create(['unit_id' => $unitLevy->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitLevy->id,
        'ledger_id' => $levyCt->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerL->id,
    ]);

    $unitRent = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => 'R1']);
    $ownerR   = Owner::factory()->create(['unit_id' => $unitRent->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitRent->id,
        'ledger_id' => $rentCt->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerR->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?ledger=' . $levyCt->id)
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
        ->getJson(route('api.v1.show.customers') . '?_search=XYZ-99')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('searches arrears by owner full_name', function () {
    ['user' => $user, 'owner' => $owner, 'unit' => $unit] = makeOverdueUnit();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_search=' . urlencode(substr($owner->full_name, 0, 5)))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('searches arrears by community name', function () {
    ['user' => $user, 'community' => $community, 'unit' => $unit] = makeOverdueUnit();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_search=' . urlencode(substr($community->name, 0, 5)))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('returns empty data when search term matches nothing', function () {
    makeOverdueUnit();
    $user = adminUser();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_search=ZZZNOMATCH999')
        ->assertOk()
        ->json('data');

    expect($data)->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Sorting
// ──────────────────────────────────────────────────────────────────────────────

it('sorts by overdue_amount descending by default (highest first)', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([['U1', 500], ['U2', 3000], ['U3', 1500]] as [$num, $amt]) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'amount' => $amt, 'billing_period' => '2026-01-01',
        ]);
    }

    $amounts = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('data.*.overdue_amount');

    expect((float) $amounts[0])->toBeGreaterThanOrEqual((float) $amounts[1]);
    expect((float) $amounts[1])->toBeGreaterThanOrEqual((float) $amounts[2]);
});

it('sorts by overdue_amount ascending', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([['U1', 500], ['U2', 3000], ['U3', 1500]] as [$num, $amt]) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'amount' => $amt, 'billing_period' => '2026-01-01',
        ]);
    }

    $amounts = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_sort=overdue_amount:asc')
        ->assertOk()
        ->json('data.*.overdue_amount');

    expect((float) $amounts[0])->toBeLessThanOrEqual((float) $amounts[1]);
    expect((float) $amounts[1])->toBeLessThanOrEqual((float) $amounts[2]);
});

it('sorts by unit_number ascending', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    foreach (['C3', 'A1', 'B2'] as $num) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => '2026-01-01',
        ]);
    }

    $numbers = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_sort=unit_number:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    $sorted = $numbers;
    sort($sorted);
    expect($numbers)->toBe($sorted);
});

it('sorts by unit_number descending', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    foreach (['A1', 'C3', 'B2'] as $num) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => '2026-01-01',
        ]);
    }

    $numbers = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_sort=unit_number:desc')
        ->assertOk()
        ->json('data.*.unit_number');

    $sorted = $numbers;
    rsort($sorted);
    expect($numbers)->toBe($sorted);
});

it('sorts by oldest_overdue ascending (earliest due date first)', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    foreach ([['U1', '2026-03-01'], ['U2', '2026-01-01'], ['U3', '2026-02-01']] as [$num, $due]) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => $num]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'due_date' => $due, 'billing_period' => '2026-01-01',
        ]);
    }

    $dates = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_sort=oldest_overdue:asc')
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $oldUnit = Unit::factory()->create([
        'community_id'       => $community->id,
        'organization_id' => $user->organization_id,
        'created_at'      => now()->subMonths(2),
    ]);
    $oldOwner = Owner::factory()->create(['unit_id' => $oldUnit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $oldUnit->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $oldOwner->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_date_range=today')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($todayUnit->id);
    expect($ids)->not->toContain($oldUnit->id);
});

it('date range custom returns only units created in the specified window', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $inRange = Unit::factory()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
        'created_at' => '2026-02-15',
    ]);
    $inOwner = Owner::factory()->create(['unit_id' => $inRange->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $inRange->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $inOwner->id,
    ]);

    $outRange = Unit::factory()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
        'created_at' => '2026-04-20',
    ]);
    $outOwner = Owner::factory()->create(['unit_id' => $outRange->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $outRange->id,
        'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $outOwner->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_date_range=custom&_date_range_start=2026-02-01&_date_range_end=2026-02-28')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($inRange->id);
    expect($ids)->not->toContain($outRange->id);
});

it('date range all_time returns all overdue units', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnit();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_date_range=all_time')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Pagination
// ──────────────────────────────────────────────────────────────────────────────

it('paginates results with _per_page', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    foreach (range(1, 5) as $i) {
        $unit  = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => "U$i"]);
        $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
        Invoice::factory()->overdue()->create([
            'organization_id' => $user->organization_id, 'unit_id' => $unit->id,
            'ledger_id' => $ct->id, 'billed_to_type' => 'owner', 'billed_to_id' => $owner->id,
            'billing_period' => '2026-01-01',
        ]);
    }

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers') . '?_per_page=2')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.per_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(5);
    expect($response->json('meta.last_page'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// Communities dropdown
// ──────────────────────────────────────────────────────────────────────────────

it('communities dropdown contains all occupant communities regardless of arrears status', function () {
    ['user' => $user, 'community' => $communityWithArrears] = makeOverdueUnit();

    $emptyCommunity = Community::factory()->create(['organization_id' => $user->organization_id]);

    $communityIds = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('communities.*.id');

    expect($communityIds)->toContain($communityWithArrears->id);
    expect($communityIds)->toContain($emptyCommunity->id);
});

it('communities dropdown does not include other occupants communities', function () {
    ['user' => $user] = makeOverdueUnit();

    $otherOrg   = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $otherOrg->id]);

    $communityIds = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('communities.*.id');

    expect($communityIds)->not->toContain($otherCommunity->id);
});

it('communities dropdown entry has id, name and entity_type fields', function () {
    ['user' => $user] = makeOverdueUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('communities.0');

    expect($entry)->toHaveKeys(['id', 'name', 'entity_type']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Ledgers dropdown
// ──────────────────────────────────────────────────────────────────────────────

it('ledgers dropdown only includes active ledgers', function () {
    ['user' => $user] = makeOverdueUnit();

    $inactiveCt = Ledger::factory()->inactive()->create(['organization_id' => $user->organization_id]);

    $codes = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('ledgers.*.id');

    expect($codes)->not->toContain($inactiveCt->id);
});

it('ledgers dropdown entry has id and name fields', function () {
    ['user' => $user] = makeOverdueUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('ledgers.0');

    expect($entry)->toHaveKeys(['id', 'name']);
});

it('ledgers dropdown does not include other occupants ledgers', function () {
    ['user' => $user] = makeOverdueUnit();

    $otherOrg = createOrganization();
    $otherCt  = Ledger::factory()->create(['organization_id' => $otherOrg->id]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('ledgers.*.id');

    expect($ids)->not->toContain($otherCt->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.total_overdue_amount is occupant-scoped
// ──────────────────────────────────────────────────────────────────────────────

it('total_overdue_amount does not include other occupants overdue invoices', function () {
    ['user' => $user]     = makeOverdueUnit(500.0);  // own org
    makeOverdueUnit(99999.0);                         // different org — must not bleed in

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customers'))
        ->assertOk()
        ->json('summary.total_overdue_amount');

    expect((float) $total)->toBe(500.0);
});
