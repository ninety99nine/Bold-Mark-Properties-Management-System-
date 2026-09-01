<?php

use App\Enums\InvoiceStatus;
use App\Models\CashbookEntry;
use App\Models\Ledger;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// GET /dashboard
// ──────────────────────────────────────────────────────────────────────────────

it('dashboard returns 401 when unauthenticated', function () {
    $this->getJson(route('api.v1.show.dashboard'))->assertUnauthorized();
});

it('dashboard returns the three top-level keys', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->assertJsonStructure(['summary', 'recent_invoices', 'communities_overview']);
});

it('dashboard returns a debt_trend with a 6-point series ending at total outstanding', function () {
    $user        = adminUser();
    $community   = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger      = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'       => $ledger->id,
        'status'          => InvoiceStatus::UNPAID->value,
        'amount'          => 1500,
        'billing_period'  => now()->startOfMonth()->toDateString(),
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);

    $trend = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('debt_trend');

    expect($trend)->toHaveKeys(['total', 'percent_change', 'series']);
    expect($trend['series'])->toHaveCount(6);
    expect((float) $trend['total'])->toBe(1500.0);
    // The final point of the cumulative series equals the current total outstanding.
    expect((float) end($trend['series'])['value'])->toBe(1500.0);
});

it('dashboard returns a compliance breakdown with the WeConnectU status split', function () {
    $user = adminUser();

    $compliance = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('compliance');

    expect($compliance)->toHaveKeys([
        'compliant', 'non_compliant', 'planned', 'unplanned', 'total', 'percent_compliant',
    ]);
});

it('dashboard returns a tasks matrix with month columns and per-metric rows', function () {
    $user = adminUser();

    $tasks = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('tasks');

    expect($tasks)->toHaveKeys(['months', 'total', 'active_overdue', 'complete', 'strike_rate']);

    // Columns run Jan → current month for the active year; every row aligns to them.
    expect($tasks['months'])->toHaveCount((int) now()->month);
    expect($tasks['total'])->toHaveCount((int) now()->month);
    expect($tasks['active_overdue'])->toHaveCount((int) now()->month);
    expect($tasks['complete'])->toHaveCount((int) now()->month);
    expect($tasks['strike_rate'])->toHaveCount((int) now()->month);

    // No Tasks module yet — every bucket is zeroed.
    expect(array_sum($tasks['total']))->toBe(0);
});

it('dashboard summary has all expected metric keys', function () {
    $user = adminUser();

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary');

    expect($summary)->toHaveKeys([
        'total_communities',
        'total_units',
        'total_outstanding',
        'unpaid_invoices_count',
        'collected_this_month',
        'payments_this_month_count',
        'occupied_units',
        'vacant_units',
        'occupancy_rate',
        'total_cashbook_entries',
    ]);
});

it('dashboard summary total_communities counts only the authenticated occupant communities', function () {
    $userA   = adminUser();
    Community::factory()->count(3)->create(['organization_id' => $userA->organization_id]);

    $userB = adminUser();
    Community::factory()->count(5)->create(['organization_id' => $userB->organization_id]);

    $countA = $this->actingAs($userA, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.total_communities');

    $countB = $this->actingAs($userB, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.total_communities');

    expect($countA)->toBe(3);
    expect($countB)->toBe(5);
});

it('dashboard summary total_units counts only the authenticated occupant units', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->count(4)->create([
        'community_id'       => $community->id,
        'organization_id' => $user->organization_id,
    ]);

    $otherUser   = adminUser();
    $otherCommunity = Community::factory()->create(['organization_id' => $otherUser->organization_id]);
    Unit::factory()->count(10)->create([
        'community_id'       => $otherCommunity->id,
        'organization_id' => $otherUser->organization_id,
    ]);

    $count = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.total_units');

    expect($count)->toBe(4);
});

it('dashboard summary total_outstanding sums unpaid and overdue invoice amounts', function () {
    $user        = adminUser();
    $community      = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger  = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ledger->id,
        'status'          => InvoiceStatus::UNPAID->value,
        'amount'          => 1000,
        'billing_period'  => now()->startOfMonth()->toDateString(),
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ledger->id,
        'amount'          => 2000,
        'billing_period'  => now()->subMonth()->startOfMonth()->toDateString(),
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);

    $outstanding = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.total_outstanding');

    expect((float) $outstanding)->toBe(3000.0);
});

it('dashboard summary total_outstanding excludes paid invoices', function () {
    $user        = adminUser();
    $community      = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger  = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ledger->id,
        'status'          => InvoiceStatus::PAID->value,
        'amount'          => 5000,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);

    $outstanding = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.total_outstanding');

    expect((float) $outstanding)->toBe(0.0);
});

it('dashboard summary occupancy_rate is 0 when there are no units', function () {
    $user = adminUser();

    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.occupancy_rate');

    expect((float) $rate)->toBe(0.0);
});

it('dashboard summary occupancy_rate reflects occupied vs total', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->count(3)->create([
        'community_id'       => $community->id,
        'organization_id' => $user->organization_id,
        'occupancy_type'  => 'occupant_occupied',
    ]);
    Unit::factory()->count(1)->create([
        'community_id'       => $community->id,
        'organization_id' => $user->organization_id,
        'occupancy_type'  => 'vacant',
    ]);

    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.occupancy_rate');

    // 3 occupied / 4 total = 75%
    expect((float) $rate)->toBe(75.0);
});

it('dashboard recent_invoices returns at most 10 entries', function () {
    $user        = adminUser();
    $community      = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger  = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    // Use a deterministic sequence spanning years so billing_period is always unique.
    Invoice::factory()->count(15)->sequence(fn ($s) => [
        'billing_period' => \Carbon\Carbon::create(2020 + intdiv($s->index, 12), ($s->index % 12) + 1, 1)->toDateString(),
    ])->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ledger->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);

    $invoices = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('recent_invoices');

    expect(count($invoices))->toBe(10);
});

it('dashboard recent_invoices each entry has expected fields', function () {
    $user        = adminUser();
    $community      = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger  = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ledger->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('recent_invoices.0');

    expect($item)->toHaveKeys([
        'id', 'invoice_number', 'status', 'amount',
        'ledger', 'unit_number', 'billing_period', 'due_date', 'billed_to_name',
    ]);
});

it('dashboard recent_invoices does not include other occupants invoices', function () {
    $otherUser   = adminUser();
    $otherCommunity = Community::factory()->create(['organization_id' => $otherUser->organization_id]);
    $otherUnit   = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $otherUser->organization_id]);
    $ct          = Ledger::factory()->create(['organization_id' => $otherUser->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $otherUser->organization_id, 'unit_id' => $otherUnit->id]);
    $otherInvoice = Invoice::factory()->create([
        'organization_id' => $otherUser->organization_id,
        'unit_id'         => $otherUnit->id,
        'ledger_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);

    $myUser = adminUser();

    $ids = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('recent_invoices.*.id');

    expect((array) $ids)->not->toContain($otherInvoice->id);
});

it('dashboard communities_overview lists occupant communities', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Test Community']);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('communities_overview.*.name');

    expect($names)->toContain('Test Community');
});

it('dashboard communities_overview each entry has occupancy breakdown fields', function () {
    $user   = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id]);

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('communities_overview.0');

    expect($item)->toHaveKeys([
        'id', 'name', 'entity_type', 'country', 'units_count',
        'owner_occupied_count', 'occupant_occupied_count', 'vacant_count',
    ]);
});

it('dashboard communities_overview does not include other occupants communities', function () {
    $otherUser = adminUser();
    Community::factory()->create(['organization_id' => $otherUser->organization_id, 'name' => 'Other Community']);

    $myUser = adminUser();

    $names = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('communities_overview.*.name');

    expect((array) $names)->not->toContain('Other Community');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /dashboard/countries
// ──────────────────────────────────────────────────────────────────────────────

it('countries returns 401 when unauthenticated', function () {
    $this->getJson(route('api.v1.show.countries'))->assertUnauthorized();
});

it('countries returns countries, default_country, and supported keys', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.countries'))
        ->assertOk()
        ->assertJsonStructure(['countries', 'default_country', 'supported']);
});

it('countries lists country codes from occupant communities', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'ZA']);
    Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW']);

    $codes = collect(
        $this->actingAs($user, 'api')
            ->getJson(route('api.v1.show.countries'))
            ->assertOk()
            ->json('countries')
    )->pluck('code')->toArray();

    expect($codes)->toContain('ZA');
    expect($codes)->toContain('BW');
});

it('countries does not include codes from other occupant communities', function () {
    $otherUser = adminUser();
    Community::factory()->create(['organization_id' => $otherUser->organization_id, 'country' => 'ZA']);

    $myUser = adminUser();

    $codes = collect(
        $this->actingAs($myUser, 'api')
            ->getJson(route('api.v1.show.countries'))
            ->assertOk()
            ->json('countries')
    )->pluck('code')->toArray();

    expect($codes)->not->toContain('ZA');
});

it('countries each entry has a code field', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'ZA']);

    $first = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.countries'))
        ->assertOk()
        ->json('countries.0');

    expect($first)->toHaveKey('code');
});
