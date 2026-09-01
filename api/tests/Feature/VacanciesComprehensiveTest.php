<?php

use App\Models\Community;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a vacant unit with all required dependencies.
 * Returns ['user', 'community', 'unit'].
 */
function makeVacantUnit(array $unitOverrides = [], array $communityOverrides = []): array
{
    $user   = adminUser();
    $community = Community::factory()->create(array_merge(
        ['organization_id' => $user->organization_id],
        $communityOverrides
    ));
    $unit = Unit::factory()->vacant()->create(array_merge([
        'community_id'       => $community->id,
        'organization_id' => $user->organization_id,
    ], $unitOverrides));

    return compact('user', 'community', 'unit');
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
        ->assertJsonStructure(['data', 'summary', 'communities', 'meta']);
});

it('returns all summary keys', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk();

    expect($response->json('summary'))->toHaveKeys([
        'total_vacant', 'total_units', 'vacancy_rate', 'communities_affected',
        'by_community_type', 'by_community', 'occupancy_per_community',
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

    expect($item)->toHaveKeys(['id', 'unit_number', 'community_id', 'organization_id', 'occupancy_type']);
    expect($item['id'])->toBe($unit->id);
    expect($item['occupancy_type'])->toBe('vacant');
});

it('unit data includes the loaded community relationship', function () {
    ['user' => $user, 'community' => $community] = makeVacantUnit();

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.0');

    expect($item['community'])->not->toBeNull();
    expect($item['community']['id'])->toBe($community->id);
    expect($item['community'])->toHaveKeys(['id', 'name', 'entity_type']);
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

    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $occupied = Unit::factory()->ownerOccupied()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->ownerOccupied()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

it('excludes occupant-occupied units', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->occupantOccupied()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids ?? [])->not->toContain($unit->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Occupant isolation
// ──────────────────────────────────────────────────────────────────────────────

it('only returns vacant units belonging to the authenticated occupant', function () {
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

it('returns empty data and zero summary when occupant has no vacant units', function () {
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $totalVacant = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_vacant');

    expect($totalVacant)->toBe(1);
});

it('summary total_units counts all occupant units regardless of occupancy', function () {
    ['user' => $user] = makeVacantUnit();

    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $totalUnits = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_units');

    expect($totalUnits)->toBe(2);
});

it('summary vacancy_rate is calculated correctly', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    // 1 vacant out of 4 total = 25%
    $rate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.vacancy_rate');

    expect((float) $rate)->toBe(25.0);
});

it('summary communities_affected counts distinct communities with vacant units', function () {
    $user = adminUser();

    foreach (range(1, 3) as $_) {
        $community = Community::factory()->create(['organization_id' => $user->organization_id]);
        Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    }

    $affected = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.communities_affected');

    expect($affected)->toBe(3);
});

it('summary communities_affected only counts communities with at least one vacant unit', function () {
    $user = adminUser();

    $communityWithVacant = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['community_id' => $communityWithVacant->id, 'organization_id' => $user->organization_id]);

    $communityFullyOccupied = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $communityFullyOccupied->id, 'organization_id' => $user->organization_id]);

    $affected = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.communities_affected');

    expect($affected)->toBe(1);
});

it('summary is scoped to the authenticated occupant — other orgs do not affect counts', function () {
    ['user' => $user] = makeVacantUnit(); // 1 vacant in own org
    makeVacantUnit();                     // 1 vacant in another org

    $totalVacant = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_vacant');

    expect($totalVacant)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.by_community_type
// ──────────────────────────────────────────────────────────────────────────────

it('by_community_type is keyed by community type string with a count', function () {
    $user   = adminUser();
    $community = Community::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $byType = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_community_type');

    expect(array_key_exists('body_corporate', $byType))->toBeTrue();
    expect((int) $byType['body_corporate'])->toBe(1);
});

it('by_community_type separates counts across different community types', function () {
    $user = adminUser();

    $sectional   = Community::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    $residential = Community::factory()->residentialRental()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->vacant()->create(['community_id' => $sectional->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['community_id' => $sectional->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['community_id' => $residential->id, 'organization_id' => $user->organization_id]);

    $byType = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_community_type');

    expect((int) $byType['body_corporate'])->toBe(2);
    expect((int) $byType['residential_rental'])->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.by_community
// ──────────────────────────────────────────────────────────────────────────────

it('by_community contains an entry for each community with vacant units', function () {
    ['user' => $user, 'community' => $community] = makeVacantUnit();

    $byCommunity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_community');

    $ids = array_column($byCommunity, 'id');
    expect($ids)->toContain($community->id);
});

it('by_community entry has correct structure', function () {
    makeVacantUnit();
    ['user' => $user] = makeVacantUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_community.0');

    expect($entry)->toHaveKeys(['id', 'name', 'entity_type', 'vacant_count']);
});

it('by_community vacant_count reflects number of vacant units per community', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $byCommunity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.by_community');

    $row = collect($byCommunity)->firstWhere('id', $community->id);
    expect($row['vacant_count'])->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// summary.occupancy_per_community
// ──────────────────────────────────────────────────────────────────────────────

it('occupancy_per_community has correct structure', function () {
    ['user' => $user] = makeVacantUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.occupancy_per_community.0');

    expect($entry)->toHaveKeys(['id', 'name', 'vacant', 'occupied']);
});

it('occupancy_per_community counts vacant and occupied correctly', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->occupantOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $perCommunity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.occupancy_per_community');

    $row = collect($perCommunity)->firstWhere('id', $community->id);
    expect($row['vacant'])->toBe(2);
    expect($row['occupied'])->toBe(2);
});

it('occupancy_per_community includes all communities even those with no vacancies', function () {
    $user           = adminUser();
    $fullyOccupied  = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $fullyOccupied->id, 'organization_id' => $user->organization_id]);

    $perCommunity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.occupancy_per_community');

    $communityIds = array_column($perCommunity, 'id');
    expect($communityIds)->toContain($fullyOccupied->id);

    $row = collect($perCommunity)->firstWhere('id', $fullyOccupied->id);
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

it('lost_revenue uses community admin_fund_amount and default_rent_amount when unit has no overrides', function () {
    $user   = adminUser();
    $community = Community::factory()->create([
        'organization_id'    => $user->organization_id,
        'admin_fund_amount' => 2000,
        'default_rent_amount' => 5000,
    ]);
    Unit::factory()->vacant()->create([
        'community_id'       => $community->id,
        'organization_id' => $user->organization_id,
        'levy_override'   => null,
        'rent_amount'     => null,
    ]);

    $lostRevenue = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.lost_revenue');

    $row = collect($lostRevenue)->firstWhere('id', $community->id);
    expect((float) $row['lost_monthly'])->toBe(7000.0); // 2000 levy + 5000 rent
});

it('lost_revenue uses unit levy_override when set instead of community default', function () {
    $user   = adminUser();
    $community = Community::factory()->create([
        'organization_id'    => $user->organization_id,
        'admin_fund_amount' => 2000,
        'default_rent_amount' => 5000,
    ]);
    Unit::factory()->vacant()->create([
        'community_id'       => $community->id,
        'organization_id' => $user->organization_id,
        'levy_override'   => 1500, // overrides community default of 2000
        'rent_amount'     => null,
    ]);

    $lostRevenue = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.lost_revenue');

    $row = collect($lostRevenue)->firstWhere('id', $community->id);
    expect((float) $row['lost_monthly'])->toBe(6500.0); // 1500 levy_override + 5000 default_rent
});

it('total_lost_revenue is the sum of all communities lost_monthly', function () {
    $user    = adminUser();
    $communityA = Community::factory()->create([
        'organization_id' => $user->organization_id, 'admin_fund_amount' => 1000, 'default_rent_amount' => 2000,
    ]);
    $communityB = Community::factory()->create([
        'organization_id' => $user->organization_id, 'admin_fund_amount' => 500, 'default_rent_amount' => 1500,
    ]);
    Unit::factory()->vacant()->create(['community_id' => $communityA->id, 'organization_id' => $user->organization_id, 'levy_override' => null, 'rent_amount' => null]);
    Unit::factory()->vacant()->create(['community_id' => $communityB->id, 'organization_id' => $user->organization_id, 'levy_override' => null, 'rent_amount' => null]);

    $totalLost = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('summary.total_lost_revenue');

    // communityA: 1000+2000=3000, communityB: 500+1500=2000, total=5000
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
// Filtering — community_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters data to a specific community_id', function () {
    $user    = adminUser();
    $communityA = Community::factory()->create(['organization_id' => $user->organization_id]);
    $communityB = Community::factory()->create(['organization_id' => $user->organization_id]);

    $unitA = Unit::factory()->vacant()->create(['community_id' => $communityA->id, 'organization_id' => $user->organization_id]);
    $unitB = Unit::factory()->vacant()->create(['community_id' => $communityB->id, 'organization_id' => $user->organization_id]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?community_id=' . $communityA->id)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($unitA->id);
    expect($ids)->not->toContain($unitB->id);
});

it('community_id filter returns empty data when that community has no vacancies', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?community_id=' . $community->id)
        ->assertOk()
        ->json('data');

    expect($data)->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering — community_type
// ──────────────────────────────────────────────────────────────────────────────

it('filters data by community_type', function () {
    $user      = adminUser();
    $sectional = Community::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    $rental    = Community::factory()->residentialRental()->create(['organization_id' => $user->organization_id]);

    $unitS = Unit::factory()->vacant()->create(['community_id' => $sectional->id, 'organization_id' => $user->organization_id]);
    $unitR = Unit::factory()->vacant()->create(['community_id' => $rental->id, 'organization_id' => $user->organization_id]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?community_type=body_corporate')
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

it('searches vacancies by community name', function () {
    ['user' => $user, 'community' => $community, 'unit' => $unit] = makeVacantUnit([], ['name' => 'Sunset Heights Community']);

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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    foreach (['C3', 'A1', 'B2'] as $num) {
        Unit::factory()->vacant()->create([
            'community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => $num,
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    foreach (['A1', 'C3', 'B2'] as $num) {
        Unit::factory()->vacant()->create([
            'community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => $num,
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

it('sorts by community_name ascending', function () {
    $user = adminUser();

    foreach (['Zenith Park', 'Amber Court', 'Morning Glen'] as $name) {
        $community = Community::factory()->create(['organization_id' => $user->organization_id, 'name' => $name]);
        Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    }

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_sort=community_name:asc')
        ->assertOk()
        ->json('data');

    $names = array_column(array_column($data, 'community'), 'name');
    $sorted = $names;
    sort($sorted);
    expect($names)->toBe($sorted);
});

it('sorts by community_name descending', function () {
    $user = adminUser();

    foreach (['Zenith Park', 'Amber Court', 'Morning Glen'] as $name) {
        $community = Community::factory()->create(['organization_id' => $user->organization_id, 'name' => $name]);
        Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    }

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies') . '?_sort=community_name:desc')
        ->assertOk()
        ->json('data');

    $names = array_column(array_column($data, 'community'), 'name');
    $sorted = $names;
    rsort($sorted);
    expect($names)->toBe($sorted);
});

it('default sort is by created_at descending (newest first)', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $old = Unit::factory()->vacant()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
        'created_at' => now()->subDays(5),
    ]);
    $new = Unit::factory()->vacant()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $todayUnit = Unit::factory()->vacant()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
        'created_at' => now(),
    ]);
    $oldUnit = Unit::factory()->vacant()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $inRange  = Unit::factory()->vacant()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
        'created_at' => '2026-03-15',
    ]);
    $outRange = Unit::factory()->vacant()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $old = Unit::factory()->vacant()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
        'created_at' => now()->subYear(),
    ]);
    $new = Unit::factory()->vacant()->create([
        'community_id' => $community->id, 'organization_id' => $user->organization_id,
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
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    foreach (range(1, 6) as $i) {
        Unit::factory()->vacant()->create([
            'community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => "V$i",
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

it('meta total reflects only the current occupant vacancies', function () {
    ['user' => $user] = makeVacantUnit();
    makeVacantUnit(); // different org

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('meta.total');

    expect($total)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Communities dropdown
// ──────────────────────────────────────────────────────────────────────────────

it('communities dropdown contains all occupant communities regardless of vacancy status', function () {
    ['user' => $user, 'community' => $withVacancy] = makeVacantUnit();

    $noVacancy = Community::factory()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->ownerOccupied()->create(['community_id' => $noVacancy->id, 'organization_id' => $user->organization_id]);

    $communityIds = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('communities.*.id');

    expect($communityIds)->toContain($withVacancy->id);
    expect($communityIds)->toContain($noVacancy->id);
});

it('communities dropdown does not include other occupants communities', function () {
    ['user' => $user] = makeVacantUnit();

    $otherOrg   = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $otherOrg->id]);

    $communityIds = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('communities.*.id');

    expect($communityIds)->not->toContain($otherCommunity->id);
});

it('communities dropdown entry has id, name and entity_type fields', function () {
    ['user' => $user] = makeVacantUnit();

    $entry = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('communities.0');

    expect($entry)->toHaveKeys(['id', 'name', 'entity_type']);
});

it('communities dropdown entity_type is returned as a plain string', function () {
    $user   = adminUser();
    $community = Community::factory()->sectionalTitle()->create(['organization_id' => $user->organization_id]);
    Unit::factory()->vacant()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $types = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.vacancies'))
        ->assertOk()
        ->json('communities.*.entity_type');

    expect($types[0])->toBeString();
    expect($types[0])->toBe('body_corporate');
});
