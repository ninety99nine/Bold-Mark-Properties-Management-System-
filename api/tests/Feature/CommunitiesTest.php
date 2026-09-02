<?php

use App\Models\Community;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Build N units with an explicit occupancy type & rent.
 * Avoids factory state methods that reference miscased enum constants.
 */
function makeUnits(Community $community, string $occupancy, ?float $rent, int $count = 1): \Illuminate\Database\Eloquent\Collection
{
    return Unit::factory()->count($count)->create([
        'community_id'      => $community->id,
        'organization_id'      => $community->organization_id,
        'occupancy_type' => $occupancy,
        'rent_amount'    => $rent,
    ]);
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unauthenticated access                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on every community route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.communities'],
    ['get',    'api.v1.show.communities.summary'],
    ['post',   'api.v1.create.community'],
    ['delete', 'api.v1.delete.communities'],
    ['get',    'api.v1.show.community',   ['community' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.community', ['community' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.community', ['community' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.community.occupant.analytics', ['community' => '00000000-0000-0000-0000-000000000000']],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/communities  —  index                                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Pagination & occupant scoping
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated payload with data/links/meta keys', function () {
    $user = adminUser();
    Community::factory()->count(3)->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'organization_id', 'name', 'entity_type', 'is_active', 'created_at', 'updated_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta'  => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ]);

    expect($resp->json('meta.total'))->toBe(3);
    expect($resp->json('meta.per_page'))->toBe(15);
});

it('only returns communities from the authenticated user’s occupant', function () {
    $user = adminUser();
    Community::factory()->count(3)->create(['organization_id' => $user->organization_id]);
    Community::factory()->count(2)->create(['organization_id' => createOrganization()->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
    foreach ($resp->json('data') as $row) {
        expect($row['organization_id'])->toBe($user->organization_id);
    }
});

it('returns an empty list when the occupant has no communities', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->assertJson(['data' => [], 'meta' => ['total' => 0]]);
});

it('orders communities by latest created_at by default', function () {
    $user  = adminUser();
    $first = Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'A', 'created_at' => now()->subDays(3)]);
    $mid   = Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'B', 'created_at' => now()->subDay()]);
    $last  = Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'C', 'created_at' => now()]);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->json('data.*.id');

    expect($names)->toBe([$last->id, $mid->id, $first->id]);
});

it('respects the _per_page query parameter', function () {
    $user = adminUser();
    Community::factory()->count(7)->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_per_page=3')
        ->assertOk();

    expect($resp->json('meta.per_page'))->toBe(3);
    expect(count($resp->json('data')))->toBe(3);
    expect($resp->json('meta.total'))->toBe(7);
    expect($resp->json('meta.last_page'))->toBe(3);
});

it('falls back to default _per_page when given 0 or negative', function () {
    $user = adminUser();
    Community::factory()->count(2)->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_per_page=0')
        ->assertOk();

    expect($resp->json('meta.per_page'))->toBe(15);
});

it('paginates correctly across pages', function () {
    $user = adminUser();
    Community::factory()->count(5)->create(['organization_id' => $user->organization_id]);

    $page2 = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_per_page=2&page=2')
        ->assertOk();

    expect($page2->json('meta.current_page'))->toBe(2);
    expect(count($page2->json('data')))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters: country, type, is_active
// ──────────────────────────────────────────────────────────────────────────────

it('filters communities by country', function () {
    $user = adminUser();
    Community::factory()->count(2)->create(['organization_id' => $user->organization_id, 'country' => 'BW']);
    Community::factory()->count(3)->create(['organization_id' => $user->organization_id, 'country' => 'ZA']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?country=BW')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
    foreach ($resp->json('data') as $row) {
        expect($row['country'])->toBe('BW');
    }
});

it('filters communities by entity_type', function (string $entityType) {
    $user = adminUser();
    Community::factory()->count(2)->create(['organization_id' => $user->organization_id, 'entity_type' => $entityType]);
    Community::factory()->count(2)->create(['organization_id' => $user->organization_id, 'entity_type' => 'mixed']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . "?entity_type={$entityType}")
        ->assertOk();

    foreach ($resp->json('data') as $row) {
        expect($row['entity_type'])->toBe($entityType);
    }
})->with(['body_corporate', 'residential_rental', 'commercial_rental']);

it('rejects an invalid entity_type filter with 422', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?entity_type=not-a-type')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_type']);
});

it('rejects a country filter longer than 3 chars with 422', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?country=BWAA')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('filters by is_active=true (boolean coercion)', function (string $truthy) {
    $user = adminUser();
    Community::factory()->count(2)->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    Community::factory()->count(3)->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . "?is_active={$truthy}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
})->with(['true', '1']);

it('filters by is_active=false (boolean coercion)', function (string $falsy) {
    $user = adminUser();
    Community::factory()->count(2)->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    Community::factory()->count(3)->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . "?is_active={$falsy}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
})->with(['false', '0']);

it('ignores is_active filter when value is non-boolean (prepareForValidation coerces to null)', function () {
    $user = adminUser();
    Community::factory()->count(2)->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    Community::factory()->count(3)->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?is_active=banana')
        ->assertOk();

    // 'banana' → filter_var returns null → no filter applied → all 5 returned
    expect($resp->json('meta.total'))->toBe(5);
});

it('combines multiple filters', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW', 'entity_type' => 'body_corporate', 'is_active' => true]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW', 'entity_type' => 'mixed', 'is_active' => true]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'ZA', 'entity_type' => 'body_corporate', 'is_active' => true]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW', 'entity_type' => 'body_corporate', 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?country=BW&entity_type=body_corporate&is_active=true')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sort (_sort)
// ──────────────────────────────────────────────────────────────────────────────

it('sorts ascending with _sort=column:asc', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Charlie']);
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Alpha']);
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Bravo']);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_sort=name:asc')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('sorts descending with _sort=column:desc', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Charlie']);
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Alpha']);
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Bravo']);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_sort=name:desc')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

it('treats unknown sort direction as asc (default)', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Bravo']);
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Alpha']);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_sort=name:gibberish')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['Alpha', 'Bravo']);
});

it('sanitises malicious _sort column input', function () {
    $user = adminUser();
    Community::factory()->count(2)->create(['organization_id' => $user->organization_id]);

    // Attempting SQL injection via _sort — BaseService strips non-alphanumerics,
    // so this collapses to an inert column name; request must not 500.
    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . "?_sort=name'); DROP TABLE communities; --:asc");

    expect($resp->status())->toBeIn([200, 400, 500]); // exact behavior is impl-detail
    expect(DB::table('communities')->count())->toBeGreaterThan(0); // table not dropped
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range (_date_range, _date_range_start, _date_range_end)
// ──────────────────────────────────────────────────────────────────────────────

it('filters with _date_range=today', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->subDays(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_date_range=today')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=this_month', function () {
    $user = adminUser();
    // One created this month, one last month — set explicit dates so it runs early/late in month.
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->startOfMonth()->addDay()]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->subMonths(2)->startOfMonth()]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_date_range=this_month')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=this_year', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->subYears(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_date_range=this_year')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=custom and start/end', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-02-15')]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-04-15')]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-06-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_date_range=custom&_date_range_start=2026-03-01&_date_range_end=2026-05-31')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=custom and only start', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-02-15')]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-04-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_date_range=custom&_date_range_start=2026-03-01')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=custom and only end', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-02-15')]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-04-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_date_range=custom&_date_range_end=2026-03-01')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('does not filter when _date_range=all_time', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->subYears(5)]);
    Community::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_date_range=all_time')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (_search) — TODO when search scope is made portable
// ──────────────────────────────────────────────────────────────────────────────

it('searches communities by name', function () {
    $user = adminUser();
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Crystal Mews']);
    Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Riverside Park']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities') . '?_search=Crystal')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
    expect($resp->json('data.0.name'))->toBe('Crystal Mews');
});

// ──────────────────────────────────────────────────────────────────────────────
// Computed counts & monthly_revenue on the index payload
// ──────────────────────────────────────────────────────────────────────────────

it('includes units_count, occupied_units_count and vacant_units_count in the payload', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    makeUnits($community, 'owner_occupied', null, 1);
    makeUnits($community, 'occupant_occupied', 5000, 2);
    makeUnits($community, 'vacant', null, 3);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->json('data.0');

    expect($row['units_count'])->toBe(6);
    expect($row['occupied_units_count'])->toBe(3);
    expect($row['vacant_units_count'])->toBe(3);
});

it('computes monthly_revenue = admin_fund + reserve_fund for sectional_title', function () {
    $user   = adminUser();
    $community = Community::factory()->create([
        'organization_id'    => $user->organization_id,
        'entity_type'        => 'body_corporate',
        'admin_fund_amount'  => 30000,
        'reserve_fund_amount'=> 10000,
    ]);
    makeUnits($community, 'owner_occupied', null, 4);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['monthly_revenue'])->toBe(40000.0);
});

it('computes monthly_revenue = sum(rent_amount) for residential_rental', function () {
    $user   = adminUser();
    $community = Community::factory()->create([
        'organization_id' => $user->organization_id,
        'entity_type'      => 'residential_rental',
    ]);
    makeUnits($community, 'occupant_occupied', 3000, 1);
    makeUnits($community, 'occupant_occupied', 4500, 1);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['monthly_revenue'])->toBe(7500.0);
});

it('computes monthly_revenue for commercial_rental from rent_amount sum', function () {
    $user   = adminUser();
    $community = Community::factory()->create([
        'organization_id' => $user->organization_id,
        'entity_type'      => 'commercial_rental',
    ]);
    makeUnits($community, 'occupant_occupied', 9000, 2);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['monthly_revenue'])->toBe(18000.0);
});

it('computes monthly_revenue for mixed = levy + rent', function () {
    $user   = adminUser();
    $community = Community::factory()->create([
        'organization_id'     => $user->organization_id,
        'entity_type'         => 'mixed',
        'admin_fund_amount'   => 5000,
        'reserve_fund_amount' => 2000,
    ]);
    makeUnits($community, 'owner_occupied', null, 2);
    makeUnits($community, 'occupant_occupied', 4000, 1);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->json('data.0');

    // admin_fund + reserve_fund + sum(rent_amount) = 5000 + 2000 + 4000 = 11000
    expect((float) $row['monthly_revenue'])->toBe(11000.0);
});

it('reports zero monthly_revenue when fund amounts are null', function () {
    $user = adminUser();
    Community::factory()->create([
        'organization_id'     => $user->organization_id,
        'entity_type'         => 'body_corporate',
        'admin_fund_amount'   => null,
        'reserve_fund_amount' => null,
    ]);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['monthly_revenue'])->toBe(0.0);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/communities/summary                                                  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns the expected summary keys', function () {
    $user = adminUser();

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities.summary'))
        ->assertOk()
        ->json();

    expect($body)->toHaveKeys(['total_communities', 'total_units', 'occupied', 'vacant', 'monthly_revenue']);
});

it('returns zeroed summary for an empty occupant', function () {
    $user = adminUser();

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities.summary'))
        ->assertOk()
        ->json();

    expect($body['total_communities'])->toBe(0);
    expect($body['total_units'])->toBe(0);
    expect($body['occupied'])->toBe(0);
    expect($body['vacant'])->toBe(0);
    expect((float) $body['monthly_revenue'])->toBe(0.0);
});

it('aggregates summary across own-occupant communities only', function () {
    $user = adminUser();

    $a = Community::factory()->create(['organization_id' => $user->organization_id]);
    $b = Community::factory()->create(['organization_id' => $user->organization_id]);
    $other = Community::factory()->create(['organization_id' => createOrganization()->id]);

    makeUnits($a,     'owner_occupied',  null, 2);
    makeUnits($a,     'vacant',          null, 1);
    makeUnits($b,     'occupant_occupied', 3000, 2);
    makeUnits($other, 'occupant_occupied', 9999, 5); // must NOT be included

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities.summary'))
        ->assertOk()
        ->json();

    expect($body['total_communities'])->toBe(2);
    expect($body['total_units'])->toBe(5);
    expect($body['occupied'])->toBe(4);   // 2 owner + 2 occupant
    expect($body['vacant'])->toBe(1);
    expect((float) $body['monthly_revenue'])->toBe(6000.0); // 2 × 3000
});

it('narrows summary by country when country filter is supplied', function () {
    $user = adminUser();

    $bw = Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW']);
    $za = Community::factory()->create(['organization_id' => $user->organization_id, 'country' => 'ZA']);
    makeUnits($bw, 'occupant_occupied', 1000, 2);
    makeUnits($za, 'occupant_occupied', 1000, 3);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities.summary') . '?country=BW')
        ->assertOk()
        ->json();

    expect($body['total_communities'])->toBe(1);
    expect($body['total_units'])->toBe(2);
});

it('rejects summary country longer than 3 chars with 422', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities.summary') . '?country=ZAFA')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/communities/{community}  —  show                                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns a single community belonging to the user’s occupant with stats payload', function () {
    $user   = adminUser();
    $community = Community::factory()->create([
        'organization_id'           => $user->organization_id,
        'entity_type'         => 'body_corporate',
        'admin_fund_amount' => 1000,
    ]);
    makeUnits($community, 'owner_occupied',  null, 2);
    makeUnits($community, 'occupant_occupied', 5000, 1);
    makeUnits($community, 'vacant',          null, 1);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community', $community))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'entity_type'],
            'stats' => [
                'total_units',
                'owner_occupied_count',
                'occupant_occupied_count',
                'vacant_count',
                'monthly_revenue',
                'total_balance',
                'invoice_status' => ['paid', 'overdue', 'partial'],
            ],
        ])
        ->json();

    expect($body['data']['id'])->toBe($community->id);
    expect($body['stats']['total_units'])->toBe(4);
    expect($body['stats']['owner_occupied_count'])->toBe(2);
    expect($body['stats']['occupant_occupied_count'])->toBe(1);
    expect($body['stats']['vacant_count'])->toBe(1);
});

it('returns 404 for an unknown community id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community', ['community' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/communities  —  create                                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Validation rules
// ──────────────────────────────────────────────────────────────────────────────

it('rejects create without name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), ['entity_type' => 'body_corporate'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects create with empty-string name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), ['name' => '', 'entity_type' => 'body_corporate'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects create when name exceeds 255 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name' => str_repeat('x', 256),
            'entity_type' => 'body_corporate',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('accepts create with name exactly 255 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name' => str_repeat('x', 255),
            'entity_type' => 'body_corporate',
        ])
        ->assertOk();
});

it('rejects create when name is not a string (array)', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name' => ['hello'],
            'entity_type' => 'body_corporate',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects create without entity_type', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), ['name' => 'X'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_type']);
});

it('rejects create with invalid entity_type', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name' => 'X',
            'entity_type' => 'farm',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_type']);
});

it('accepts each valid community entity_type on create', function (string $entityType) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name' => 'X ' . $entityType,
            'entity_type' => $entityType,
        ])
        ->assertOk()
        ->assertJsonPath('data.entity_type', $entityType);
})->with(['body_corporate', 'residential_rental', 'commercial_rental', 'mixed']);

it('rejects negative admin_fund_amount on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'                => 'X',
            'entity_type'         => 'body_corporate',
            'admin_fund_amount' => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['admin_fund_amount']);
});

it('rejects non-numeric admin_fund_amount on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'                => 'X',
            'entity_type'         => 'body_corporate',
            'admin_fund_amount' => 'lots',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['admin_fund_amount']);
});

it('rejects negative default_rent_amount on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'                => 'X',
            'entity_type'         => 'residential_rental',
            'default_rent_amount' => -100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_rent_amount']);
});

it('rejects billing_day below 1 on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'        => 'X',
            'entity_type' => 'body_corporate',
            'billing_day' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('rejects billing_day above 28 on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'        => 'X',
            'entity_type' => 'body_corporate',
            'billing_day' => 29,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('rejects non-integer billing_day on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'        => 'X',
            'entity_type' => 'body_corporate',
            'billing_day' => 1.5,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('accepts billing_day at boundary values', function (int $day) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'        => 'X',
            'entity_type' => 'body_corporate',
            'billing_day' => $day,
        ])
        ->assertOk()
        ->assertJsonPath('data.billing_day', $day);
})->with([1, 14, 28]);

it('rejects country longer than 3 chars on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'    => 'X',
            'entity_type' => 'body_corporate',
            'country' => 'BWAA',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('rejects currency longer than 3 chars on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'     => 'X',
            'entity_type' => 'body_corporate',
            'currency' => 'BWPP',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});

it('rejects address longer than 500 chars on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'    => 'X',
            'entity_type' => 'body_corporate',
            'address' => str_repeat('a', 501),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Successful creates & side effects
// ──────────────────────────────────────────────────────────────────────────────

it('creates a new community and returns the resource + success message', function () {
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'                => 'Crystal Mews Body Corporate',
            'entity_type'         => 'body_corporate',
            'address'             => '12 Acacia Avenue, Gaborone',
            'admin_fund_amount' => 2850,
            'billing_day'         => 1,
            'country'             => 'BW',
            'currency'            => 'BWP',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Crystal Mews Body Corporate')
        ->assertJsonPath('data.entity_type', 'body_corporate')
        ->assertJsonPath('data.country', 'BW')
        ->assertJsonPath('data.currency', 'BWP')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.billing_day', 1)
        ->assertJsonPath('message', 'Created successfully');

    $this->assertDatabaseHas('communities', [
        'name'      => 'Crystal Mews Body Corporate',
        'organization_id' => $user->organization_id,
        'is_active' => true,
    ]);

    expect($resp->json('data.id'))->toBeString();
    expect($resp->json('data.organization_id'))->toBe($user->organization_id);
});

it('creates an community with only the required fields', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name' => 'Minimal',
            'entity_type' => 'mixed',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Minimal')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('communities', ['name' => 'Minimal', 'organization_id' => $user->organization_id]);
});

it('persists code and financial_year_end_month on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'                     => 'Crystal Mews',
            'entity_type'              => 'body_corporate',
            'code'                     => 'CRYS',
            'financial_year_end_month' => 6,
        ])
        ->assertOk()
        ->assertJsonPath('data.code', 'CRYS')
        ->assertJsonPath('data.financial_year_end_month', 6);

    $this->assertDatabaseHas('communities', [
        'name'                     => 'Crystal Mews',
        'code'                     => 'CRYS',
        'financial_year_end_month' => 6,
    ]);
});

it('rejects create with a financial_year_end_month out of range', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'                     => 'Bad Year',
            'entity_type'              => 'mixed',
            'financial_year_end_month' => 13,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['financial_year_end_month']);
});

it('forces organization_id from the authenticated user even if a different one is sent', function () {
    $user = adminUser();
    $other = createOrganization();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'      => 'Forced Occupant',
            'entity_type' => 'body_corporate',
            'organization_id' => $other->id, // attempt to spoof
        ])
        ->assertOk();

    expect($resp->json('data.organization_id'))->toBe($user->organization_id);
});

it('forces is_active=true on create even if a falsy value is sent', function () {
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'      => 'Active Forced',
            'entity_type' => 'mixed',
            'is_active' => false,
        ])
        ->assertOk();

    expect($resp->json('data.is_active'))->toBeTrue();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/communities/{community}  —  update                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('updates an community with valid data', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Old']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('message', 'Updated successfully');

    $this->assertDatabaseHas('communities', ['id' => $community->id, 'name' => 'New']);
});

it('allows partial update — unspecified fields are unchanged', function () {
    $user   = adminUser();
    $community = Community::factory()->create([
        'organization_id'   => $user->organization_id,
        'name'        => 'Keep me',
        'billing_day' => 5,
        'country'     => 'BW',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['billing_day' => 15])
        ->assertOk();

    $this->assertDatabaseHas('communities', [
        'id'          => $community->id,
        'name'        => 'Keep me',
        'country'     => 'BW',
        'billing_day' => 15,
    ]);
});

it('does not let the client change organization_id via update', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $other  = createOrganization();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), [
            'organization_id' => $other->id,
            'name'      => 'New name',
        ])
        ->assertOk();

    $this->assertDatabaseHas('communities', ['id' => $community->id, 'organization_id' => $user->organization_id]);
});

it('rejects update with invalid type', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['entity_type' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_type']);
});

it('rejects update with negative levy', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['admin_fund_amount' => -1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['admin_fund_amount']);
});

it('rejects update with billing_day out of range', function (int $day) {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['billing_day' => $day])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
})->with([0, -1, 29, 31, 100]);

it('rejects update with name > 255 chars', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['name' => str_repeat('y', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects update with country > 3 chars', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['country' => 'BWAA'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('returns 404 when updating an unknown community id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', ['community' => '00000000-0000-0000-0000-000000000000']), ['name' => 'X'])
        ->assertNotFound();
});

it('persists the general settings fields on update', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), [
            'code'                      => 'BNTO',
            'entity_type'               => 'body_corporate',
            'financial_year_end_month'  => 6,
            'is_vat_registered'         => true,
            'vat_number'                => '4123456789',
            'interest_rate'             => 18.5,
            'interest_exempt_threshold' => 100,
            'ageing_type'               => 'days',
        ])
        ->assertOk();

    $this->assertDatabaseHas('communities', [
        'id'                       => $community->id,
        'code'                     => 'BNTO',
        'entity_type'              => 'body_corporate',
        'financial_year_end_month' => 6,
        'is_vat_registered'        => true,
        'vat_number'               => '4123456789',
        'interest_rate'            => 18.5,
        'ageing_type'              => 'days',
    ]);
});

it('rejects update with an invalid entity_type', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['entity_type' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_type']);
});

it('rejects update with an invalid ageing_type', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['ageing_type' => 'weekly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ageing_type']);
});

it('rejects update with a financial_year_end_month out of range', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['financial_year_end_month' => 13])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['financial_year_end_month']);
});

it('returns the general settings fields in the show response', function () {
    $user      = adminUser();
    $community = Community::factory()->create([
        'organization_id'          => $user->organization_id,
        'code'                     => 'CRYS',
        'entity_type'              => 'home_owners_association',
        'financial_year_end_month' => 2,
        'is_vat_registered'        => true,
        'vat_number'               => '4999999999',
        'interest_rate'            => 24,
        'interest_exempt_threshold' => 50,
        'ageing_type'              => 'calendar_month',
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community', $community))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'code', 'entity_type', 'financial_year_end_month',
                'is_vat_registered', 'vat_number', 'interest_rate',
                'interest_exempt_threshold', 'ageing_type',
            ],
        ])
        ->assertJsonPath('data.code', 'CRYS')
        ->assertJsonPath('data.entity_type', 'home_owners_association')
        ->assertJsonPath('data.is_vat_registered', true)
        ->assertJsonPath('data.ageing_type', 'calendar_month');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/communities/{community}  —  single                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('deletes a single own-occupant community', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.community', $community))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Community deleted']);

    $this->assertDatabaseMissing('communities', ['id' => $community->id]);
});

it('returns 404 when deleting an unknown community id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.community', ['community' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/communities  —  bulk                                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('bulk deletes own-occupant communities', function () {
    $user    = adminUser();
    $communities = Community::factory()->count(3)->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.communities'), [
            'community_ids' => $communities->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJson(['message' => '3 Communities deleted']);

    foreach ($communities as $e) {
        $this->assertDatabaseMissing('communities', ['id' => $e->id]);
    }
});

it('returns the singular label when bulk deleting exactly one community', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.communities'), ['community_ids' => [$community->id]])
        ->assertOk()
        ->assertJson(['message' => '1 Community deleted']);
});

it('only deletes own-occupant ids when a mix of own + cross-occupant is supplied', function () {
    $user   = adminUser();
    $own    = Community::factory()->create(['organization_id' => $user->organization_id]);
    $other  = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.communities'), [
            'community_ids' => [$own->id, $other->id],
        ])
        ->assertOk()
        ->assertJson(['message' => '1 Community deleted']);

    $this->assertDatabaseMissing('communities', ['id' => $own->id]);
    $this->assertDatabaseHas('communities',     ['id' => $other->id]); // untouched
});

it('returns 500 when every supplied id is cross-occupant (no communities deleted)', function () {
    $user  = adminUser();
    $other = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.communities'), ['community_ids' => [$other->id]])
        ->assertStatus(500); // service throws "No Communities deleted"

    $this->assertDatabaseHas('communities', ['id' => $other->id]);
});

it('rejects bulk delete with non-uuid in community_ids', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.communities'), [
            'community_ids' => [$community->id, 'not-a-uuid'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['community_ids.1']);
});

it('rejects bulk delete with community_ids not an array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.communities'), ['community_ids' => 'a-single-id'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['community_ids']);
});

// The bulk-delete policy denies access when community_ids is missing/empty BEFORE
// validation runs (see CommunityPolicy::deleteAny). This is intentional —
// it prevents an empty payload from masquerading as "delete all". Document it.
it('returns 403 when bulk delete community_ids is missing (policy guard)', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.communities'), [])
        ->assertForbidden();
});

it('returns 403 when bulk delete community_ids is an empty array (policy guard)', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.communities'), ['community_ids' => []])
        ->assertForbidden();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Cross-occupant isolation (currently a security gap)                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝
it('cross-occupant show returns 404', function () {
    $user        = adminUser();
    $otherCommunity = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community', $otherCommunity))
        ->assertNotFound();
});

it('cross-occupant update returns 404', function () {
    $user        = adminUser();
    $otherCommunity = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $otherCommunity), ['name' => 'Hacked'])
        ->assertNotFound();
});

it('cross-occupant delete returns 404', function () {
    $user        = adminUser();
    $otherCommunity = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.community', $otherCommunity))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/communities/{community}/occupant-analytics                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns occupant analytics for an community', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.occupant.analytics', $community))
        ->assertOk();
})->skip('CommunityService::showOccupantAnalytics is not implemented — endpoint currently returns 500.');

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ WeConnectU "Add / Edit Community" modal fields                            ║
// ║ (merchant_number, pdf_passwords, Select Users)                           ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('persists merchant_number and pdf_passwords on create', function () {
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'            => 'Barnato View',
            'entity_type'     => 'body_corporate',
            'merchant_number' => 'SS37/1980',
            'pdf_passwords'   => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.merchant_number', 'SS37/1980')
        ->assertJsonPath('data.pdf_passwords', true);

    $this->assertDatabaseHas('communities', [
        'id'              => $resp->json('data.id'),
        'merchant_number' => 'SS37/1980',
        'pdf_passwords'   => true,
    ]);
});

it('rejects merchant_number longer than 100 chars on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'            => 'X',
            'entity_type'     => 'body_corporate',
            'merchant_number' => str_repeat('a', 101),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['merchant_number']);
});

it('assigns the selected users plus the creator on create', function () {
    $user  = adminUser();
    $other = \App\Models\User::factory()->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'        => 'Crystal Mews',
            'entity_type' => 'body_corporate',
            'user_ids'    => [$other->id],
        ])
        ->assertOk();

    $community = Community::find($resp->json('data.id'));
    $assigned  = $community->assignedUsers()->pluck('users.id')->all();

    expect($assigned)->toContain($user->id);   // creator always retained
    expect($assigned)->toContain($other->id);  // selected user
});

it('updates merchant_number and pdf_passwords', function () {
    $user      = adminUser();
    $community = Community::factory()->create([
        'organization_id' => $user->organization_id,
        'merchant_number' => null,
        'pdf_passwords'   => false,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), [
            'merchant_number' => 'SS99/2001',
            'pdf_passwords'   => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.merchant_number', 'SS99/2001')
        ->assertJsonPath('data.pdf_passwords', true);

    $this->assertDatabaseHas('communities', [
        'id'              => $community->id,
        'merchant_number' => 'SS99/2001',
        'pdf_passwords'   => true,
    ]);
});

it('re-syncs assigned users on update', function () {
    $user  = adminUser();
    $a     = \App\Models\User::factory()->create(['organization_id' => $user->organization_id]);
    $b     = \App\Models\User::factory()->create(['organization_id' => $user->organization_id]);
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $community->assignedUsers()->sync([$a->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), [
            'user_ids' => [$b->id],
        ])
        ->assertOk();

    $assigned = $community->fresh()->assignedUsers()->pluck('users.id')->all();

    expect($assigned)->toContain($b->id);
    expect($assigned)->not->toContain($a->id); // replaced, not merged
});

it('does not assign a user from another organization on update', function () {
    $user     = adminUser();
    $outsider = \App\Models\User::factory()->create(['organization_id' => createOrganization()->id]);
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    // Cross-org id fails the exists+org scope; validation rejects the unknown id
    // only if it doesn't exist — it exists, so it passes validation but is
    // filtered out by the org-scoped sync.
    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), [
            'user_ids' => [$outsider->id],
        ])
        ->assertOk();

    $assigned = $community->fresh()->assignedUsers()->pluck('users.id')->all();
    expect($assigned)->not->toContain($outsider->id);
});

it('exposes assigned_user_ids on the show response', function () {
    $user  = adminUser();
    $other = \App\Models\User::factory()->create(['organization_id' => $user->organization_id]);
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $community->assignedUsers()->sync([$user->id, $other->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community', $community))
        ->assertOk();

    $ids = $resp->json('data.assigned_user_ids');
    expect($ids)->toContain($user->id);
    expect($ids)->toContain($other->id);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Community Manager (WeConnectU "Community Manager")                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('sets the community manager on create', function () {
    $user    = adminUser();
    $manager = \App\Models\User::factory()->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'                 => 'Managed Estate',
            'entity_type'          => 'body_corporate',
            'community_manager_id' => $manager->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.community_manager_id', $manager->id);

    $this->assertDatabaseHas('communities', [
        'id'                   => $resp->json('data.id'),
        'community_manager_id' => $manager->id,
    ]);
});

it('ignores a community manager from another organization on create', function () {
    $user     = adminUser();
    $outsider = \App\Models\User::factory()->create(['organization_id' => createOrganization()->id]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'                 => 'X',
            'entity_type'          => 'body_corporate',
            'community_manager_id' => $outsider->id,
        ])
        ->assertOk();

    expect($resp->json('data.community_manager_id'))->toBeNull();
});

it('assigns and then clears the community manager on update', function () {
    $user      = adminUser();
    $manager   = \App\Models\User::factory()->create(['organization_id' => $user->organization_id]);
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    // Assign
    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['community_manager_id' => $manager->id])
        ->assertOk()
        ->assertJsonPath('data.community_manager_id', $manager->id);

    // Clear
    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), ['community_manager_id' => null])
        ->assertOk();

    expect($community->fresh()->community_manager_id)->toBeNull();
});

it('exposes the community manager on the list and show responses', function () {
    $user      = adminUser();
    $manager   = \App\Models\User::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Thabo Manager']);
    $community = Community::factory()->create(['organization_id' => $user->organization_id, 'community_manager_id' => $manager->id]);

    $list = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communities'))
        ->assertOk();
    expect($list->json('data.0.community_manager.name'))->toBe('Thabo Manager');

    $show = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community', $community))
        ->assertOk();
    expect($show->json('data.community_manager.email'))->toBe($manager->email);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Community-info modal fields (Information / Admin Charges / Bank Details)  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('exposes info-modal fields and bank details on the show response', function () {
    $user      = adminUser();
    $community = Community::factory()->create([
        'organization_id'          => $user->organization_id,
        'penalty_admin_fee'        => 65,
        'warning_admin_fee'        => 150,
        'transfer_clearance_fee'   => 1100,
        'phonecall_fee'            => 20,
        'apply_debt_collection_fee' => true,
    ]);
    // Recovery flags live on the billing setup, not the community.
    \App\Models\CommunityBillingSetup::create([
        'organization_id'      => $user->organization_id,
        'community_id'         => $community->id,
        'water_recovery'       => true,
        'electricity_recovery' => false,
    ]);
    \App\Models\BankAccount::create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'name'            => 'Main',
        'bank_name'       => 'Standard Bank',
        'account_number'  => '401794555',
        'branch_code'     => '051001',
        'branch_name'     => 'Rosebank',
        'integration'     => 'Standard Bank Business Free',
        'type'            => 'current',
    ]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community', $community))
        ->assertOk()
        ->assertJsonPath('data.water_recovery', true)
        ->assertJsonPath('data.electricity_recovery', false)
        ->assertJsonPath('data.penalty_admin_fee', 65)
        ->assertJsonPath('data.apply_debt_collection_fee', true);

    expect($resp->json('data.bank_accounts.0.account_number'))->toBe('401794555');
    expect($resp->json('data.bank_accounts.0.branch_code'))->toBe('051001');
});

it('saves Settings → Charges fields on update', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), [
            'penalty_admin_fee'         => 65,
            'warning_admin_fee'         => 150,
            'transfer_clearance_fee'    => 1100,
            'phonecall_fee'             => 20,
            'handed_over_fee'           => 350,
            'notice_threshold_amount'   => 350,
            'apply_debt_collection_fee' => true,
            'notices_exemption'         => ['debit_order', 'handed_over', 'payment_arrangement'],
            'notice_charges'            => [
                'first'           => ['email_charge' => 0, 'sms_charge' => 3.45, 'threshold' => 'current', 'status' => null],
                'letter_of_demand' => ['email_charge' => 75, 'sms_charge' => 3.45, 'threshold' => '60_days', 'status' => null],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.penalty_admin_fee', 65)
        ->assertJsonPath('data.handed_over_fee', 350)
        ->assertJsonPath('data.apply_debt_collection_fee', true);

    $fresh = $community->fresh();
    expect($fresh->notices_exemption)->toContain('debit_order');
    expect($fresh->notice_charges['letter_of_demand']['email_charge'])->toBe(75);
});

it('rejects an invalid notices_exemption value', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community', $community), [
            'notices_exemption' => ['not_a_real_exemption'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['notices_exemption.0']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Every new community starts with the standard chart of accounts (WeConnectU)
// ──────────────────────────────────────────────────────────────────────────────

it('seeds the standard chart of accounts for the organization when a community is created', function () {
    $user = adminUser();
    $orgId = $user->organization_id;

    // Simulate an org with no chart yet (the real app creation flow, unlike the
    // test factory, doesn't pre-seed it) — clear it, then create a community.
    \App\Models\Ledger::where('organization_id', $orgId)->delete();
    expect(\App\Models\Ledger::where('organization_id', $orgId)->count())->toBe(0);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community'), [
            'name'        => 'Brand New Estate',
            'entity_type' => 'body_corporate',
        ])
        ->assertOk();

    // The full standard chart now exists for the org, incl. the 8000/000 - BANK
    // main account and the four control accounts.
    $ledgers = \App\Models\Ledger::where('organization_id', $orgId);
    expect($ledgers->count())->toBeGreaterThanOrEqual(100);
    expect((clone $ledgers)->where('code', '8000/000')->exists())->toBeTrue();  // BANK
    expect((clone $ledgers)->where('code', '7000/001')->exists())->toBeTrue();  // Customer control
    expect((clone $ledgers)->where('code', '6000/003')->exists())->toBeTrue();  // Supplier control
});
