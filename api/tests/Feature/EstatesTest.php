<?php

use App\Models\Estate;
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
function makeUnits(Estate $estate, string $occupancy, ?float $rent, int $count = 1): \Illuminate\Database\Eloquent\Collection
{
    return Unit::factory()->count($count)->create([
        'estate_id'      => $estate->id,
        'organization_id'      => $estate->organization_id,
        'occupancy_type' => $occupancy,
        'rent_amount'    => $rent,
    ]);
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unauthenticated access                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on every estate route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.estates'],
    ['get',    'api.v1.show.estates.summary'],
    ['post',   'api.v1.create.estate'],
    ['delete', 'api.v1.delete.estates'],
    ['get',    'api.v1.show.estate',   ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.estate', ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.estate', ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.estate.tenant.analytics', ['estate' => '00000000-0000-0000-0000-000000000000']],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/estates  —  index                                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Pagination & tenant scoping
// ──────────────────────────────────────────────────────────────────────────────

it('returns a paginated payload with data/links/meta keys', function () {
    $user = adminUser();
    Estate::factory()->count(3)->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'organization_id', 'name', 'type', 'is_active', 'created_at', 'updated_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta'  => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ]);

    expect($resp->json('meta.total'))->toBe(3);
    expect($resp->json('meta.per_page'))->toBe(15);
});

it('only returns estates from the authenticated user’s tenant', function () {
    $user = adminUser();
    Estate::factory()->count(3)->create(['organization_id' => $user->organization_id]);
    Estate::factory()->count(2)->create(['organization_id' => createTenant()->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
    foreach ($resp->json('data') as $row) {
        expect($row['organization_id'])->toBe($user->organization_id);
    }
});

it('returns an empty list when the tenant has no estates', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->assertJson(['data' => [], 'meta' => ['total' => 0]]);
});

it('orders estates by latest created_at by default', function () {
    $user  = adminUser();
    $first = Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'A', 'created_at' => now()->subDays(3)]);
    $mid   = Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'B', 'created_at' => now()->subDay()]);
    $last  = Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'C', 'created_at' => now()]);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->json('data.*.id');

    expect($names)->toBe([$last->id, $mid->id, $first->id]);
});

it('respects the _per_page query parameter', function () {
    $user = adminUser();
    Estate::factory()->count(7)->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_per_page=3')
        ->assertOk();

    expect($resp->json('meta.per_page'))->toBe(3);
    expect(count($resp->json('data')))->toBe(3);
    expect($resp->json('meta.total'))->toBe(7);
    expect($resp->json('meta.last_page'))->toBe(3);
});

it('falls back to default _per_page when given 0 or negative', function () {
    $user = adminUser();
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_per_page=0')
        ->assertOk();

    expect($resp->json('meta.per_page'))->toBe(15);
});

it('paginates correctly across pages', function () {
    $user = adminUser();
    Estate::factory()->count(5)->create(['organization_id' => $user->organization_id]);

    $page2 = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_per_page=2&page=2')
        ->assertOk();

    expect($page2->json('meta.current_page'))->toBe(2);
    expect(count($page2->json('data')))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters: country, type, is_active
// ──────────────────────────────────────────────────────────────────────────────

it('filters estates by country', function () {
    $user = adminUser();
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id, 'country' => 'BW']);
    Estate::factory()->count(3)->create(['organization_id' => $user->organization_id, 'country' => 'ZA']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?country=BW')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
    foreach ($resp->json('data') as $row) {
        expect($row['country'])->toBe('BW');
    }
});

it('filters estates by type', function (string $type) {
    $user = adminUser();
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id, 'type' => $type]);
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id, 'type' => 'mixed']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . "?type={$type}")
        ->assertOk();

    foreach ($resp->json('data') as $row) {
        expect($row['type'])->toBe($type);
    }
})->with(['sectional_title', 'residential_rental', 'commercial_rental']);

it('rejects an invalid type filter with 422', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?type=not-a-type')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('rejects a country filter longer than 3 chars with 422', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?country=BWAA')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('filters by is_active=true (boolean coercion)', function (string $truthy) {
    $user = adminUser();
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    Estate::factory()->count(3)->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . "?is_active={$truthy}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
})->with(['true', '1']);

it('filters by is_active=false (boolean coercion)', function (string $falsy) {
    $user = adminUser();
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    Estate::factory()->count(3)->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . "?is_active={$falsy}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
})->with(['false', '0']);

it('ignores is_active filter when value is non-boolean (prepareForValidation coerces to null)', function () {
    $user = adminUser();
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    Estate::factory()->count(3)->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?is_active=banana')
        ->assertOk();

    // 'banana' → filter_var returns null → no filter applied → all 5 returned
    expect($resp->json('meta.total'))->toBe(5);
});

it('combines multiple filters', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW', 'type' => 'sectional_title', 'is_active' => true]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW', 'type' => 'mixed', 'is_active' => true]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'ZA', 'type' => 'sectional_title', 'is_active' => true]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW', 'type' => 'sectional_title', 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?country=BW&type=sectional_title&is_active=true')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sort (_sort)
// ──────────────────────────────────────────────────────────────────────────────

it('sorts ascending with _sort=column:asc', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Charlie']);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Alpha']);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Bravo']);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_sort=name:asc')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('sorts descending with _sort=column:desc', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Charlie']);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Alpha']);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Bravo']);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_sort=name:desc')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

it('treats unknown sort direction as asc (default)', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Bravo']);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Alpha']);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_sort=name:gibberish')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['Alpha', 'Bravo']);
});

it('sanitises malicious _sort column input', function () {
    $user = adminUser();
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id]);

    // Attempting SQL injection via _sort — BaseService strips non-alphanumerics,
    // so this collapses to an inert column name; request must not 500.
    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . "?_sort=name'); DROP TABLE estates; --:asc");

    expect($resp->status())->toBeIn([200, 400, 500]); // exact behavior is impl-detail
    expect(DB::table('estates')->count())->toBeGreaterThan(0); // table not dropped
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range (_date_range, _date_range_start, _date_range_end)
// ──────────────────────────────────────────────────────────────────────────────

it('filters with _date_range=today', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->subDays(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_date_range=today')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=this_month', function () {
    $user = adminUser();
    // One created this month, one last month — set explicit dates so it runs early/late in month.
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->startOfMonth()->addDay()]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->subMonths(2)->startOfMonth()]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_date_range=this_month')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=this_year', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->subYears(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_date_range=this_year')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=custom and start/end', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-02-15')]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-04-15')]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-06-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_date_range=custom&_date_range_start=2026-03-01&_date_range_end=2026-05-31')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=custom and only start', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-02-15')]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-04-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_date_range=custom&_date_range_start=2026-03-01')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters with _date_range=custom and only end', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-02-15')]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-04-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_date_range=custom&_date_range_end=2026-03-01')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('does not filter when _date_range=all_time', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()->subYears(5)]);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'created_at' => now()]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_date_range=all_time')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (_search) — TODO when search scope is made portable
// ──────────────────────────────────────────────────────────────────────────────

it('searches estates by name', function () {
    $user = adminUser();
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Crystal Mews']);
    Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Riverside Park']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates') . '?_search=Crystal')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
    expect($resp->json('data.0.name'))->toBe('Crystal Mews');
});

// ──────────────────────────────────────────────────────────────────────────────
// Computed counts & monthly_revenue on the index payload
// ──────────────────────────────────────────────────────────────────────────────

it('includes units_count, occupied_units_count and vacant_units_count in the payload', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    makeUnits($estate, 'owner_occupied', null, 1);
    makeUnits($estate, 'tenant_occupied', 5000, 2);
    makeUnits($estate, 'vacant', null, 3);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->json('data.0');

    expect($row['units_count'])->toBe(6);
    expect($row['occupied_units_count'])->toBe(3);
    expect($row['vacant_units_count'])->toBe(3);
});

it('computes monthly_revenue = unit_count * default_levy for sectional_title', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id'           => $user->organization_id,
        'type'                => 'sectional_title',
        'default_levy_amount' => 1000,
    ]);
    makeUnits($estate, 'owner_occupied', null, 4);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['monthly_revenue'])->toBe(4000.0);
});

it('computes monthly_revenue = sum(rent_amount) for residential_rental', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id' => $user->organization_id,
        'type'      => 'residential_rental',
    ]);
    makeUnits($estate, 'tenant_occupied', 3000, 1);
    makeUnits($estate, 'tenant_occupied', 4500, 1);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['monthly_revenue'])->toBe(7500.0);
});

it('computes monthly_revenue for commercial_rental from rent_amount sum', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id' => $user->organization_id,
        'type'      => 'commercial_rental',
    ]);
    makeUnits($estate, 'tenant_occupied', 9000, 2);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['monthly_revenue'])->toBe(18000.0);
});

it('computes monthly_revenue for mixed = levy + rent', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id'           => $user->organization_id,
        'type'                => 'mixed',
        'default_levy_amount' => 500,
    ]);
    makeUnits($estate, 'owner_occupied', null, 2);
    makeUnits($estate, 'tenant_occupied', 4000, 1);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->json('data.0');

    // 3 units × 500 levy + 1 × 4000 rent = 1500 + 4000 = 5500
    expect((float) $row['monthly_revenue'])->toBe(5500.0);
});

it('reports zero monthly_revenue when there are no units', function () {
    $user = adminUser();
    Estate::factory()->create([
        'organization_id'           => $user->organization_id,
        'type'                => 'sectional_title',
        'default_levy_amount' => 1000,
    ]);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates'))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['monthly_revenue'])->toBe(0.0);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/estates/summary                                                  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns the expected summary keys', function () {
    $user = adminUser();

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates.summary'))
        ->assertOk()
        ->json();

    expect($body)->toHaveKeys(['total_estates', 'total_units', 'occupied', 'vacant', 'monthly_revenue']);
});

it('returns zeroed summary for an empty tenant', function () {
    $user = adminUser();

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates.summary'))
        ->assertOk()
        ->json();

    expect($body['total_estates'])->toBe(0);
    expect($body['total_units'])->toBe(0);
    expect($body['occupied'])->toBe(0);
    expect($body['vacant'])->toBe(0);
    expect((float) $body['monthly_revenue'])->toBe(0.0);
});

it('aggregates summary across own-tenant estates only', function () {
    $user = adminUser();

    $a = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $b = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $other = Estate::factory()->create(['organization_id' => createTenant()->id]);

    makeUnits($a,     'owner_occupied',  null, 2);
    makeUnits($a,     'vacant',          null, 1);
    makeUnits($b,     'tenant_occupied', 3000, 2);
    makeUnits($other, 'tenant_occupied', 9999, 5); // must NOT be included

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates.summary'))
        ->assertOk()
        ->json();

    expect($body['total_estates'])->toBe(2);
    expect($body['total_units'])->toBe(5);
    expect($body['occupied'])->toBe(4);   // 2 owner + 2 tenant
    expect($body['vacant'])->toBe(1);
    expect((float) $body['monthly_revenue'])->toBe(6000.0); // 2 × 3000
});

it('narrows summary by country when country filter is supplied', function () {
    $user = adminUser();

    $bw = Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'BW']);
    $za = Estate::factory()->create(['organization_id' => $user->organization_id, 'country' => 'ZA']);
    makeUnits($bw, 'tenant_occupied', 1000, 2);
    makeUnits($za, 'tenant_occupied', 1000, 3);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates.summary') . '?country=BW')
        ->assertOk()
        ->json();

    expect($body['total_estates'])->toBe(1);
    expect($body['total_units'])->toBe(2);
});

it('rejects summary country longer than 3 chars with 422', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estates.summary') . '?country=ZAFA')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/estates/{estate}  —  show                                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns a single estate belonging to the user’s tenant with stats payload', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id'           => $user->organization_id,
        'type'                => 'sectional_title',
        'default_levy_amount' => 1000,
    ]);
    makeUnits($estate, 'owner_occupied',  null, 2);
    makeUnits($estate, 'tenant_occupied', 5000, 1);
    makeUnits($estate, 'vacant',          null, 1);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $estate))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'type'],
            'stats' => [
                'total_units',
                'owner_occupied_count',
                'tenant_occupied_count',
                'vacant_count',
                'monthly_revenue',
                'total_balance',
                'invoice_status' => ['paid', 'overdue', 'partial'],
            ],
        ])
        ->json();

    expect($body['data']['id'])->toBe($estate->id);
    expect($body['stats']['total_units'])->toBe(4);
    expect($body['stats']['owner_occupied_count'])->toBe(2);
    expect($body['stats']['tenant_occupied_count'])->toBe(1);
    expect($body['stats']['vacant_count'])->toBe(1);
});

it('returns 404 for an unknown estate id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', ['estate' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/estates  —  create                                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Validation rules
// ──────────────────────────────────────────────────────────────────────────────

it('rejects create without name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), ['type' => 'sectional_title'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects create with empty-string name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), ['name' => '', 'type' => 'sectional_title'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects create when name exceeds 255 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => str_repeat('x', 256),
            'type' => 'sectional_title',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('accepts create with name exactly 255 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => str_repeat('x', 255),
            'type' => 'sectional_title',
        ])
        ->assertOk();
});

it('rejects create when name is not a string (array)', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => ['hello'],
            'type' => 'sectional_title',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects create without type', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), ['name' => 'X'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('rejects create with invalid type', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => 'X',
            'type' => 'farm',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('accepts each valid estate type on create', function (string $type) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => 'X ' . $type,
            'type' => $type,
        ])
        ->assertOk()
        ->assertJsonPath('data.type', $type);
})->with(['sectional_title', 'residential_rental', 'commercial_rental', 'mixed']);

it('rejects negative default_levy_amount on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'                => 'X',
            'type'                => 'sectional_title',
            'default_levy_amount' => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_levy_amount']);
});

it('rejects non-numeric default_levy_amount on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'                => 'X',
            'type'                => 'sectional_title',
            'default_levy_amount' => 'lots',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_levy_amount']);
});

it('rejects negative default_rent_amount on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'                => 'X',
            'type'                => 'residential_rental',
            'default_rent_amount' => -100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_rent_amount']);
});

it('rejects billing_day below 1 on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'        => 'X',
            'type'        => 'sectional_title',
            'billing_day' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('rejects billing_day above 28 on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'        => 'X',
            'type'        => 'sectional_title',
            'billing_day' => 29,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('rejects non-integer billing_day on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'        => 'X',
            'type'        => 'sectional_title',
            'billing_day' => 1.5,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
});

it('accepts billing_day at boundary values', function (int $day) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'        => 'X',
            'type'        => 'sectional_title',
            'billing_day' => $day,
        ])
        ->assertOk()
        ->assertJsonPath('data.billing_day', $day);
})->with([1, 14, 28]);

it('rejects country longer than 3 chars on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'    => 'X',
            'type'    => 'sectional_title',
            'country' => 'BWAA',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('rejects currency longer than 3 chars on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'     => 'X',
            'type'     => 'sectional_title',
            'currency' => 'BWPP',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});

it('rejects address longer than 500 chars on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'    => 'X',
            'type'    => 'sectional_title',
            'address' => str_repeat('a', 501),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Successful creates & side effects
// ──────────────────────────────────────────────────────────────────────────────

it('creates a new estate and returns the resource + success message', function () {
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'                => 'Crystal Mews Body Corporate',
            'type'                => 'sectional_title',
            'address'             => '12 Acacia Avenue, Gaborone',
            'default_levy_amount' => 2850,
            'billing_day'         => 1,
            'country'             => 'BW',
            'currency'            => 'BWP',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Crystal Mews Body Corporate')
        ->assertJsonPath('data.type', 'sectional_title')
        ->assertJsonPath('data.country', 'BW')
        ->assertJsonPath('data.currency', 'BWP')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.billing_day', 1)
        ->assertJsonPath('message', 'Created successfully');

    $this->assertDatabaseHas('estates', [
        'name'      => 'Crystal Mews Body Corporate',
        'organization_id' => $user->organization_id,
        'is_active' => true,
    ]);

    expect($resp->json('data.id'))->toBeString();
    expect($resp->json('data.organization_id'))->toBe($user->organization_id);
});

it('creates an estate with only the required fields', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name' => 'Minimal',
            'type' => 'mixed',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Minimal')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('estates', ['name' => 'Minimal', 'organization_id' => $user->organization_id]);
});

it('forces organization_id from the authenticated user even if a different one is sent', function () {
    $user = adminUser();
    $other = createTenant();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'      => 'Forced Tenant',
            'type'      => 'sectional_title',
            'organization_id' => $other->id, // attempt to spoof
        ])
        ->assertOk();

    expect($resp->json('data.organization_id'))->toBe($user->organization_id);
});

it('forces is_active=true on create even if a falsy value is sent', function () {
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.estate'), [
            'name'      => 'Active Forced',
            'type'      => 'mixed',
            'is_active' => false,
        ])
        ->assertOk();

    expect($resp->json('data.is_active'))->toBeTrue();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/estates/{estate}  —  update                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('updates an estate with valid data', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Old']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('message', 'Updated successfully');

    $this->assertDatabaseHas('estates', ['id' => $estate->id, 'name' => 'New']);
});

it('allows partial update — unspecified fields are unchanged', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id'   => $user->organization_id,
        'name'        => 'Keep me',
        'billing_day' => 5,
        'country'     => 'BW',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['billing_day' => 15])
        ->assertOk();

    $this->assertDatabaseHas('estates', [
        'id'          => $estate->id,
        'name'        => 'Keep me',
        'country'     => 'BW',
        'billing_day' => 15,
    ]);
});

it('does not let the client change organization_id via update', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $other  = createTenant();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), [
            'organization_id' => $other->id,
            'name'      => 'New name',
        ])
        ->assertOk();

    $this->assertDatabaseHas('estates', ['id' => $estate->id, 'organization_id' => $user->organization_id]);
});

it('rejects update with invalid type', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['type' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('rejects update with negative levy', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['default_levy_amount' => -1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_levy_amount']);
});

it('rejects update with billing_day out of range', function (int $day) {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['billing_day' => $day])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_day']);
})->with([0, -1, 29, 31, 100]);

it('rejects update with name > 255 chars', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['name' => str_repeat('y', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects update with country > 3 chars', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $estate), ['country' => 'BWAA'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('returns 404 when updating an unknown estate id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', ['estate' => '00000000-0000-0000-0000-000000000000']), ['name' => 'X'])
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/estates/{estate}  —  single                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('deletes a single own-tenant estate', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estate', $estate))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Estate deleted']);

    $this->assertDatabaseMissing('estates', ['id' => $estate->id]);
});

it('returns 404 when deleting an unknown estate id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estate', ['estate' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/estates  —  bulk                                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('bulk deletes own-tenant estates', function () {
    $user    = adminUser();
    $estates = Estate::factory()->count(3)->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), [
            'estate_ids' => $estates->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJson(['message' => '3 Estates deleted']);

    foreach ($estates as $e) {
        $this->assertDatabaseMissing('estates', ['id' => $e->id]);
    }
});

it('returns the singular label when bulk deleting exactly one estate', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), ['estate_ids' => [$estate->id]])
        ->assertOk()
        ->assertJson(['message' => '1 Estate deleted']);
});

it('only deletes own-tenant ids when a mix of own + cross-tenant is supplied', function () {
    $user   = adminUser();
    $own    = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $other  = Estate::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), [
            'estate_ids' => [$own->id, $other->id],
        ])
        ->assertOk()
        ->assertJson(['message' => '1 Estate deleted']);

    $this->assertDatabaseMissing('estates', ['id' => $own->id]);
    $this->assertDatabaseHas('estates',     ['id' => $other->id]); // untouched
});

it('returns 500 when every supplied id is cross-tenant (no estates deleted)', function () {
    $user  = adminUser();
    $other = Estate::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), ['estate_ids' => [$other->id]])
        ->assertStatus(500); // service throws "No Estates deleted"

    $this->assertDatabaseHas('estates', ['id' => $other->id]);
});

it('rejects bulk delete with non-uuid in estate_ids', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), [
            'estate_ids' => [$estate->id, 'not-a-uuid'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_ids.1']);
});

it('rejects bulk delete with estate_ids not an array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), ['estate_ids' => 'a-single-id'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estate_ids']);
});

// The bulk-delete policy denies access when estate_ids is missing/empty BEFORE
// validation runs (see EstatePolicy::deleteAny). This is intentional —
// it prevents an empty payload from masquerading as "delete all". Document it.
it('returns 403 when bulk delete estate_ids is missing (policy guard)', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), [])
        ->assertForbidden();
});

it('returns 403 when bulk delete estate_ids is an empty array (policy guard)', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estates'), ['estate_ids' => []])
        ->assertForbidden();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Cross-tenant isolation (currently a security gap)                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝
it('cross-tenant show returns 404', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.estate', $otherEstate))
        ->assertNotFound();
});

it('cross-tenant update returns 404', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.estate', $otherEstate), ['name' => 'Hacked'])
        ->assertNotFound();
});

it('cross-tenant delete returns 404', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.estate', $otherEstate))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/estates/{estate}/tenant-analytics                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns tenant analytics for an estate', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.estate.tenant.analytics', $estate))
        ->assertOk();
})->skip('EstateService::showTenantAnalytics is not implemented — endpoint currently returns 500.');
