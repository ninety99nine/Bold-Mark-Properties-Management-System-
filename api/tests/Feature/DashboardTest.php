<?php

use App\Enums\InvoiceStatus;
use App\Models\CashbookEntry;
use App\Models\ChargeType;
use App\Models\Estate;
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
        ->assertJsonStructure(['summary', 'recent_invoices', 'estates_overview']);
});

it('dashboard summary has all expected metric keys', function () {
    $user = adminUser();

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary');

    expect($summary)->toHaveKeys([
        'total_estates',
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

it('dashboard summary total_estates counts only the authenticated tenant estates', function () {
    $userA   = adminUser();
    Estate::factory()->count(3)->create(['organization_id' => $userA->organization_id]);

    $userB = adminUser();
    Estate::factory()->count(5)->create(['organization_id' => $userB->organization_id]);

    $countA = $this->actingAs($userA, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.total_estates');

    $countB = $this->actingAs($userB, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('summary.total_estates');

    expect($countA)->toBe(3);
    expect($countB)->toBe(5);
});

it('dashboard summary total_units counts only the authenticated tenant units', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->count(4)->create([
        'estate_id'       => $estate->id,
        'organization_id' => $user->organization_id,
    ]);

    $otherUser   = adminUser();
    $otherEstate = Estate::factory()->create(['organization_id' => $otherUser->organization_id]);
    Unit::factory()->count(10)->create([
        'estate_id'       => $otherEstate->id,
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
    $estate      = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType  = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'status'          => InvoiceStatus::UNPAID->value,
        'amount'          => 1000,
        'billing_period'  => now()->startOfMonth()->toDateString(),
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);
    Invoice::factory()->overdue()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
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
    $estate      = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType  = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
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
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->count(3)->create([
        'estate_id'       => $estate->id,
        'organization_id' => $user->organization_id,
        'occupancy_type'  => 'tenant_occupied',
    ]);
    Unit::factory()->count(1)->create([
        'estate_id'       => $estate->id,
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
    $estate      = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType  = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->count(15)->sequence(fn ($s) => [
        'billing_period' => now()->subMonths($s->index)->startOfMonth()->toDateString(),
    ])->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
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
    $estate      = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit        = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType  = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('recent_invoices.0');

    expect($item)->toHaveKeys([
        'id', 'invoice_number', 'status', 'amount',
        'charge_type', 'unit_number', 'billing_period', 'due_date', 'billed_to_name',
    ]);
});

it('dashboard recent_invoices does not include other tenants invoices', function () {
    $otherUser   = adminUser();
    $otherEstate = Estate::factory()->create(['organization_id' => $otherUser->organization_id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'organization_id' => $otherUser->organization_id]);
    $ct          = ChargeType::factory()->create(['organization_id' => $otherUser->organization_id]);
    $owner       = Owner::factory()->create(['organization_id' => $otherUser->organization_id, 'unit_id' => $otherUnit->id]);
    $otherInvoice = Invoice::factory()->create([
        'organization_id' => $otherUser->organization_id,
        'unit_id'         => $otherUnit->id,
        'charge_type_id'  => $ct->id,
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

it('dashboard estates_overview lists tenant estates', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Test Estate']);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('estates_overview.*.name');

    expect($names)->toContain('Test Estate');
});

it('dashboard estates_overview each entry has occupancy breakdown fields', function () {
    $user   = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id]);

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('estates_overview.0');

    expect($item)->toHaveKeys([
        'id', 'name', 'type', 'country', 'units_count',
        'owner_occupied_count', 'tenant_occupied_count', 'vacant_count',
    ]);
});

it('dashboard estates_overview does not include other tenants estates', function () {
    $otherUser = adminUser();
    Estate::factory()->create(['organization_id' => $otherUser->organization_id, 'name' => 'Other Estate']);

    $myUser = adminUser();

    $names = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk()
        ->json('estates_overview.*.name');

    expect((array) $names)->not->toContain('Other Estate');
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

it('countries lists country codes from tenant estates', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'ZA']);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW']);

    $codes = collect(
        $this->actingAs($user, 'api')
            ->getJson(route('api.v1.show.countries'))
            ->assertOk()
            ->json('countries')
    )->pluck('code')->toArray();

    expect($codes)->toContain('ZA');
    expect($codes)->toContain('BW');
});

it('countries does not include codes from other tenant estates', function () {
    $otherUser = adminUser();
    Estate::factory()->create(['organization_id' => $otherUser->organization_id, 'country' => 'ZA']);

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
    Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'ZA']);

    $first = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.countries'))
        ->assertOk()
        ->json('countries.0');

    expect($first)->toHaveKey('code');
});
