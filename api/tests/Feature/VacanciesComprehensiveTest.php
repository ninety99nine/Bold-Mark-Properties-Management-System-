<?php

use App\Models\Estate;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a vacant unit with all required dependencies.
 * Returns ['user', 'estate', 'unit'].
 */
function makeVacantUnit(array $unitOverrides = [], array $estateOverrides = []): array
{
    $user   = adminUser();
    $estate = Estate::factory()->create(array_merge(
        ['organization_id' => $user->organization_id],
        $estateOverrides
    ));
    $unit = Unit::factory()->vacant()->create(array_merge([
        'estate_id'       => $estate->id,
        'organization_id' => $user->organization_id,
    ], $unitOverrides));

    return compact('user', 'estate', 'unit');
}

// ──────────────────────────────────────────────────────────────────────────────
// Authentication
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 when unauthenticated', function () {
    $this->getJson(route('api.v1.show.vacancies'))
        ->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// Response structure
// ──────────────────────────────────────────────────────────────────────────────

it('returns all top-level keys', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->assertJsonStructure(['data', 'summary', 'estates', 'meta']);
});

it('returns all summary keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk();

    expect($response->json('summary'))->toHaveKeys([
        'total_vacant', 'total_units', 'vacancy_rate', 'estates_affected',
        'by_estate_type', 'by_estate', 'occupancy_per_estate',
        'lost_revenue', 'total_lost_revenue', 'by_duration',
    ]);
});

it('returns all by_duration bucket keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk();

    expect($response->json('summary.by_duration'))->toHaveKeys(['under_30', 'd30_90', 'd90_180', 'd180_plus']);
});

it('returns all meta pagination keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk();

    expect($response->json('meta'))->toHaveKeys(['total', 'current_page', 'last_page', 'per_page']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Unit payload fields
// ──────────────────────────────────────────────────────────────────────────────

it('each unit in data has the expected UnitResource fields', function () {
    ['user' => $user, 'unit' => $unit] = makeVacantUnit();

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.0');

    expect($item)->toHaveKeys(['id', 'unit_number', 'estate_id', 'organization_id', 'occupancy_type']);
    expect($item['id'])->toBe($unit->id);
    expect($item['occupancy_type'])->toBe('vacant');
});

it('unit data includes the loaded estate relationship', function () {
    ['user' => $user, 'estate' => $estate] = makeVacantUnit();

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.0');

    expect($item['estate'])->not->toBeNull();
    expect($item['estate']['id'])->toBe($estate->id);
    expect($item['estate'])->toHaveKeys(['id', 'name', 'type']);
});

it('unit data includes the loaded owner relationship (null when no owner)', function () {
    ['user' => $user] = makeVacantUnit();

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.0');

    // owner is always loaded (via with()), null when no owner record exists
    expect(array_key_exists('owner', $item))->toBeTrue();
});

it('unit data owner is populated when an owner exists for the unit', function () {
    ['user' => $user, 'unit' => $unit] = makeVacantUnit();
    $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.0');

    expect($item['owner'])->not->toBeNull();
    expect($item['owner']['id'])->toBe($owner->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Business rule: only vacant units appear
// ──────────────────────────────────────────────────────────────────────────────

it('only includes units with occupancy_type = vacant', function () {
    ['user' => $user, 'unit' => $vacantUnit] = makeVacantUnit();

    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $occupied = Unit::factory()->ownerOccupied()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($vacantUnit->id);
    expect($ids)->not->toContain($occupied->id);
});

it('excludes owner-occupied units', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->ownerOccupied()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

it('excludes tenant-occupied units', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->tenantOccupied()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Tenant isolation
// ──────────────────────────────────────────────────────────────────────────────

it('only returns vacant units belonging to the authenticated tenant', function () {
    ['user' => $user, 'unit' => $myUnit]   = makeVacantUnit();
    ['unit' => $otherUnit]                 = makeVacantUnit(); // different org

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($myUnit->id);
    expect($ids)->not->toContain($otherUnit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Empty state
// ──────────────────────────────────────────────────────────────────────────────

it('returns empty data and zero summary when tenant has no vacant units', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
    expect($response->json('summary.total_vacant'))->toBe(0);
    expect((float) $response->json('summary.total_lost_revenue'))->toBe(0.0);
    expect($response->json('meta.total'))->toBe(0);
});

it('vacancy_rate is 0 when there are no units at all', function () {
    $user = adminUser();

    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.vacancy_rate');

    expect($rate)->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Summary statistics
// ──────────────────────────────────────────────────────────────────────────────

it('summary total_vacant counts only vacant units', function () {
    ['user' => $user] = makeVacantUnit();

    // Add an occupied unit — should not count
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $totalVacant = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_vacant');

    expect($totalVacant)->toBe(1);
});

it('summary total_units counts all tenant units regardless of occupancy', function () {
    ['user' => $user] = makeVacantUnit();

    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $totalUnits = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_units');

    expect($totalUnits)->toBe(2);
});

it('summary vacancy_rate is calculated correctly', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    // 1 vacant out of 4 total = 25%
    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.vacancy_rate');

    expect((float) $rate)->toBe(25.0);
});

it('summary estates_affected counts distinct estates with vacant units', function () {
    $user = adminUser();

    foreach (range(1, 3) as $_) {
        $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
        Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    }

    $affected = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.estates_affected');

    expect($affected)->toBe(3);
});

it('summary estates_affected only counts estates with at least one vacant unit', function () {
    $user = adminUser();

    $estateWithVacant = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['estate_id' => $estateWithVacant->id, 'organization_id' => $user->organization_id]);

    $estateFullyOccupied = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estateFullyOccupied->id, 'organization_id' => $user->organization_id]);

    $affected = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.estates_affected');

    expect($affected)->toBe(1);
});

it('summary is scoped to the authenticated tenant — other orgs do not affect counts', function () {
    ['user' => $user] = makeVacantUnit(); // 1 vacant in own org
    makeVacantUnit();                     // 1 vacant in another org

    $totalVacant = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_vacant');

    expect($totalVacant)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.by_estate_type
// ──────────────────────────────────────────────────────────────────────────────

it('by_estate_type is keyed by estate type string with a count', function () {
    $user   = adminUser();
    $estate = Estate::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $byType = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_estate_type');

    expect(array_key_exists('sectional_title', $byType))->toBeTrue();
    expect((int) $byType['sectional_title'])->toBe(1);
});

it('by_estate_type separates counts across different estate types', function () {
    $user = adminUser();

    $sectional   = Estate::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    $residential = Estate::factory()->residentialRental()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->vacant()->create(['estate_id' => $sectional->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['estate_id' => $sectional->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['estate_id' => $residential->id, 'organization_id' => $user->organization_id]);

    $byType = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_estate_type');

    expect((int) $byType['sectional_title'])->toBe(2);
    expect((int) $byType['residential_rental'])->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.by_estate
// ──────────────────────────────────────────────────────────────────────────────

it('by_estate contains an entry for each estate with vacant units', function () {
    ['user' => $user, 'estate' => $estate] = makeVacantUnit();

    $byEstate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_estate');

    $ids = array_column($byEstate, 'id');
    expect($ids)->toContain($estate->id);
});

it('by_estate entry has correct structure', function () {
    makeVacantUnit();
    ['user' => $user] = makeVacantUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_estate.0');

    expect($entry)->toHaveKeys(['id', 'name', 'type', 'vacant_count']);
});

it('by_estate vacant_count reflects number of vacant units per estate', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $byEstate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_estate');

    $row = collect($byEstate)->firstWhere('id', $estate->id);
    expect($row['vacant_count'])->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.occupancy_per_estate
// ──────────────────────────────────────────────────────────────────────────────

it('occupancy_per_estate has correct structure', function () {
    ['user' => $user] = makeVacantUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.occupancy_per_estate.0');

    expect($entry)->toHaveKeys(['id', 'name', 'vacant', 'occupied']);
});

it('occupancy_per_estate counts vacant and occupied correctly', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->tenantOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $perEstate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.occupancy_per_estate');

    $row = collect($perEstate)->firstWhere('id', $estate->id);
    expect($row['vacant'])->toBe(2);
    expect($row['occupied'])->toBe(2);
});

it('occupancy_per_estate includes all estates even those with no vacancies', function () {
    $user           = adminUser();
    $fullyOccupied  = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $fullyOccupied->id, 'organization_id' => $user->organization_id]);

    $perEstate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.occupancy_per_estate');

    $estateIds = array_column($perEstate, 'id');
    expect($estateIds)->toContain($fullyOccupied->id);

    $row = collect($perEstate)->firstWhere('id', $fullyOccupied->id);
    expect($row['vacant'])->toBe(0);
    expect($row['occupied'])->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.lost_revenue
// ──────────────────────────────────────────────────────────────────────────────

it('lost_revenue entry has correct structure', function () {
    makeVacantUnit([], ['admin_fund_amount' => 1000, 'default_rent_amount' => 3000]);
    ['user' => $user] = makeVacantUnit([], ['admin_fund_amount' => 1000, 'default_rent_amount' => 3000]);

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.lost_revenue.0');

    expect($entry)->toHaveKeys(['id', 'name', 'lost_monthly']);
});

it('lost_revenue uses estate admin_fund_amount and default_rent_amount when unit has no overrides', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id'    => $user->organization_id,
        'admin_fund_amount' => 2000,
        'default_rent_amount' => 5000,
    ]);
    Unit::factory()->vacant()->create([
        'estate_id'       => $estate->id,
        'organization_id' => $user->organization_id,
        'levy_override'   => null,
        'rent_amount'     => null,
    ]);

    $lostRevenue = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.lost_revenue');

    $row = collect($lostRevenue)->firstWhere('id', $estate->id);
    expect((float) $row['lost_monthly'])->toBe(7000.0); // 2000 levy + 5000 rent
});

it('lost_revenue uses unit levy_override when set instead of estate default', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id'    => $user->organization_id,
        'admin_fund_amount' => 2000,
        'default_rent_amount' => 5000,
    ]);
    Unit::factory()->vacant()->create([
        'estate_id'       => $estate->id,
        'organization_id' => $user->organization_id,
        'levy_override'   => 1500, // overrides estate default of 2000
        'rent_amount'     => null,
    ]);

    $lostRevenue = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.lost_revenue');

    $row = collect($lostRevenue)->firstWhere('id', $estate->id);
    expect((float) $row['lost_monthly'])->toBe(6500.0); // 1500 levy_override + 5000 default_rent
});

it('total_lost_revenue is the sum of all estates lost_monthly', function () {
    $user    = adminUser();
    $estateA = Estate::factory()->create([
        'organization_id' => $user->organization_id, 'admin_fund_amount' => 1000, 'default_rent_amount' => 2000,
    ]);
    $estateB = Estate::factory()->create([
        'organization_id' => $user->organization_id, 'admin_fund_amount' => 500, 'default_rent_amount' => 1500,
    ]);
    Unit::factory()->vacant()->create(['estate_id' => $estateA->id, 'organization_id' => $user->organization_id, 'levy_override' => null, 'rent_amount' => null]);
    Unit::factory()->vacant()->create(['estate_id' => $estateB->id, 'organization_id' => $user->organization_id, 'levy_override' => null, 'rent_amount' => null]);

    $totalLost = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_lost_revenue');

    // estateA: 1000+2000=3000, estateB: 500+1500=2000, total=5000
    expect((float) $totalLost)->toBe(5000.0);
});

it('total_lost_revenue is 0 when no vacant units exist', function () {
    $user = adminUser();

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_lost_revenue');

    expect((float) $total)->toBe(0.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — estate_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters data to a specific estate_id', function () {
    $user    = adminUser();
    $estateA = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $estateB = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $unitA = Unit::factory()->vacant()->create(['estate_id' => $estateA->id, 'organization_id' => $user->organization_id]);
    $unitB = Unit::factory()->vacant()->create(['estate_id' => $estateB->id, 'organization_id' => $user->organization_id]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?estate_id=' . $estateA->id)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unitA->id);
    expect($ids)->not->toContain($unitB->id);
});

it('estate_id filter returns empty data when that estate has no vacancies', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?estate_id=' . $estate->id)
        ->assertOk()
        ->json('data');

    expect($data)->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — estate_type
// ──────────────────────────────────────────────────────────────────────────────

it('filters data by estate_type', function () {
    $user      = adminUser();
    $sectional = Estate::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    $rental    = Estate::factory()->residentialRental()->create(['organization_id' => $user->organization_id]);

    $unitS = Unit::factory()->vacant()->create(['estate_id' => $sectional->id, 'organization_id' => $user->organization_id]);
    $unitR = Unit::factory()->vacant()->create(['estate_id' => $rental->id, 'organization_id' => $user->organization_id]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?estate_type=sectional_title')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unitS->id);
    expect($ids)->not->toContain($unitR->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (ilike — SQLite-incompatible, skipped)
// ──────────────────────────────────────────────────────────────────────────────

it('searches vacancies by unit_number', function () {
    ['user' => $user, 'unit' => $unit] = makeVacantUnit(['unit_number' => 'V-UNIQUE-42']);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_search=V-UNIQUE-42')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('searches vacancies by owner full_name', function () {
    ['user' => $user, 'unit' => $unit] = makeVacantUnit();
    $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'full_name' => 'Zanele Mokoena']);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_search=Zanele')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

it('searches vacancies by estate name', function () {
    ['user' => $user, 'estate' => $estate, 'unit' => $unit] = makeVacantUnit([], ['name' => 'Sunset Heights Estate']);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_search=Sunset')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sorting
// ──────────────────────────────────────────────────────────────────────────────

it('sorts by unit_number ascending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    foreach (['C3', 'A1', 'B2'] as $num) {
        Unit::factory()->vacant()->create([
            'estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => $num,
        ]);
    }

    $numbers = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_sort=unit_number:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    $sorted = $numbers;
    sort($sorted);
    expect($numbers)->toBe($sorted);
});

it('sorts by unit_number descending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    foreach (['A1', 'C3', 'B2'] as $num) {
        Unit::factory()->vacant()->create([
            'estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => $num,
        ]);
    }

    $numbers = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_sort=unit_number:desc')
        ->assertOk()
        ->json('data.*.unit_number');

    $sorted = $numbers;
    rsort($sorted);
    expect($numbers)->toBe($sorted);
});

it('sorts by estate_name ascending', function () {
    $user = adminUser();

    foreach (['Zenith Park', 'Amber Court', 'Morning Glen'] as $name) {
        $estate = Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => $name]);
        Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    }

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_sort=estate_name:asc')
        ->assertOk()
        ->json('data');

    $names = array_column(array_column($data, 'estate'), 'name');
    $sorted = $names;
    sort($sorted);
    expect($names)->toBe($sorted);
});

it('sorts by estate_name descending', function () {
    $user = adminUser();

    foreach (['Zenith Park', 'Amber Court', 'Morning Glen'] as $name) {
        $estate = Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => $name]);
        Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    }

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_sort=estate_name:desc')
        ->assertOk()
        ->json('data');

    $names = array_column(array_column($data, 'estate'), 'name');
    $sorted = $names;
    rsort($sorted);
    expect($names)->toBe($sorted);
});

it('default sort is by created_at descending (newest first)', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $old = Unit::factory()->vacant()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => now()->subDays(5),
    ]);
    $new = Unit::factory()->vacant()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => now(),
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids[0])->toBe($new->id);
    expect($ids[1])->toBe($old->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range filtering (filters units.created_at)
// ──────────────────────────────────────────────────────────────────────────────

it('date range today returns only vacant units created today', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $todayUnit = Unit::factory()->vacant()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => now(),
    ]);
    $oldUnit = Unit::factory()->vacant()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => now()->subMonths(2),
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_date_range=today')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($todayUnit->id);
    expect($ids)->not->toContain($oldUnit->id);
});

it('date range custom returns only vacant units created in the specified window', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $inRange  = Unit::factory()->vacant()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => '2026-03-15',
    ]);
    $outRange = Unit::factory()->vacant()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => '2026-01-10',
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_date_range=custom&_date_range_start=2026-03-01&_date_range_end=2026-03-31')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($inRange->id);
    expect($ids)->not->toContain($outRange->id);
});

it('date range all_time returns all vacant units', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $old = Unit::factory()->vacant()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => now()->subYear(),
    ]);
    $new = Unit::factory()->vacant()->create([
        'estate_id' => $estate->id, 'organization_id' => $user->organization_id,
        'created_at' => now(),
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_date_range=all_time')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($old->id);
    expect($ids)->toContain($new->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Pagination
// ──────────────────────────────────────────────────────────────────────────────

it('paginates results with _per_page', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    foreach (range(1, 6) as $i) {
        Unit::factory()->vacant()->create([
            'estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => "V$i",
        ]);
    }

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_per_page=3')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('meta.per_page'))->toBe(3);
    expect($response->json('meta.total'))->toBe(6);
    expect($response->json('meta.last_page'))->toBe(2);
});

it('meta total reflects only the current tenant vacancies', function () {
    ['user' => $user] = makeVacantUnit();
    makeVacantUnit(); // different org

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('meta.total');

    expect($total)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Estates dropdown
// ──────────────────────────────────────────────────────────────────────────────

it('estates dropdown contains all tenant estates regardless of vacancy status', function () {
    ['user' => $user, 'estate' => $withVacancy] = makeVacantUnit();

    $noVacancy = Estate::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['estate_id' => $noVacancy->id, 'organization_id' => $user->organization_id]);

    $estateIds = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('estates.*.id');

    expect($estateIds)->toContain($withVacancy->id);
    expect($estateIds)->toContain($noVacancy->id);
});

it('estates dropdown does not include other tenants estates', function () {
    ['user' => $user] = makeVacantUnit();

    $otherOrg   = createTenant();
    $otherEstate = Estate::factory()->create(['organization_id' => $otherOrg->id]);

    $estateIds = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('estates.*.id');

    expect($estateIds)->not->toContain($otherEstate->id);
});

it('estates dropdown entry has id, name and type fields', function () {
    ['user' => $user] = makeVacantUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('estates.0');

    expect($entry)->toHaveKeys(['id', 'name', 'type']);
});

it('estates dropdown type is returned as a plain string', function () {
    $user   = adminUser();
    $estate = Estate::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $types = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('estates.*.type');

    expect($types[0])->toBeString();
    expect($types[0])->toBe('sectional_title');
});
