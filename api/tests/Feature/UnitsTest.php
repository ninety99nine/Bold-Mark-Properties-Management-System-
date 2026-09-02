<?php

use App\Models\CashbookEntry;
use App\Models\Ledger;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\Occupant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeCommunity(\App\Models\User $user, array $overrides = []): Community
{
    return Community::factory()->create(array_merge([
        'organization_id'           => $user->organization_id,
        'entity_type'         => 'residential_rental',
        'admin_fund_amount' => 1000,
    ], $overrides));
}

function makeUnit(Community $community, array $overrides = []): Unit
{
    return Unit::factory()->create(array_merge([
        'community_id' => $community->id,
        'organization_id' => $community->organization_id,
    ], $overrides));
}

function attachOwner(Unit $unit, array $overrides = []): Owner
{
    return Owner::factory()->create(array_merge([
        'unit_id'   => $unit->id,
        'organization_id' => $unit->organization_id,
    ], $overrides));
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unauthenticated access                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on every unit route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.units',           ['community' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.export.units',         ['community' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.create.unit',          ['community' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.units',         ['community' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.bulk.import.template', ['community' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.bulk.import.parse',    ['community' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.bulk.import.units',    ['community' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.show.unit',            ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.unit',          ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.unit',          ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.show.unit.activities', ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/communities/{community}/units  —  index                                 ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Pagination, scoping, charts payload
// ──────────────────────────────────────────────────────────────────────────────

it('returns the paginator structure plus a charts payload', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    Unit::factory()->count(3)->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta'  => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            'charts' => [
                'occupancy'           => ['owner_occupied', 'occupant_occupied', 'vacant'],
                'invoice_status'      => ['paid', 'overdue', 'partial'],
                'top_owner_arrears',
                'occupant_lease_expiry' => ['expired', 'this_month', 'next_month', 'in_3_months', 'beyond'],
                'top_occupant_arrears',
            ],
        ]);

    expect($resp->json('meta.total'))->toBe(3);
});

it('only returns units belonging to the requested community', function () {
    $user = adminUser();
    $a    = makeCommunity($user);
    $b    = makeCommunity($user);
    Unit::factory()->count(2)->create(['community_id' => $a->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->count(5)->create(['community_id' => $b->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $a))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
    foreach ($resp->json('data') as $row) {
        expect($row['community_id'])->toBe($a->id);
    }
});

it('returns zero counts and empty arrays in charts when the community has no units', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community))
        ->assertOk();

    expect($resp->json('data'))->toBe([]);
    expect($resp->json('charts.occupancy'))->toBe(['owner_occupied' => 0, 'occupant_occupied' => 0, 'vacant' => 0]);
    expect($resp->json('charts.top_owner_arrears'))->toBe([]);
    expect($resp->json('charts.top_occupant_arrears'))->toBe([]);
});

it('exposes every field the CommunityDetailPage table reads', function () {
    $user   = adminUser();
    $community = makeCommunity($user, ['admin_fund_amount' => 1500]);

    $unit = makeUnit($community, [
        'unit_number'    => 'A01',
        'occupancy_type' => 'occupant_occupied',
        'rent_amount'    => 7500,
        'levy_override'  => 2000,
        'balance'        => -500,
    ]);
    attachOwner($unit, ['full_name' => 'Acme Holdings', 'email' => 'acme@example.com']);
    Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Jane Doe',
        'email'     => 'jane@example.com',
    ]);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community))
        ->assertOk()
        ->json('data.0');

    expect($row)->toHaveKeys([
        'id', 'unit_number', 'occupancy_type', 'balance',
        'outstanding_amount', 'effective_levy_amount', 'rent_amount',
        'owner', 'current_occupant', 'total_occupants_count',
    ]);
    expect($row['unit_number'])->toBe('A01');
    expect($row['occupancy_type'])->toBe('occupant_occupied');
    expect((float) $row['balance'])->toBe(-500.0);
    expect((float) $row['rent_amount'])->toBe(7500.0);
    expect((float) $row['effective_levy_amount'])->toBe(2000.0);
    expect($row['owner']['full_name'])->toBe('Acme Holdings');
    expect($row['current_occupant']['full_name'])->toBe('Jane Doe');
    expect($row['total_occupants_count'])->toBe(1);
});

it('falls back to community admin_fund_amount when no levy_override is set', function () {
    $user   = adminUser();
    $community = makeCommunity($user, ['admin_fund_amount' => 1500]);
    $unit   = makeUnit($community, ['levy_override' => null]);
    attachOwner($unit);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['effective_levy_amount'])->toBe(1500.0);
});

it('orders units by unit_number ascending by default', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    foreach (['C03', 'A01', 'B02'] as $n) {
        makeUnit($community, ['unit_number' => $n]);
    }

    $numbers = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community))
        ->assertOk()
        ->json('data.*.unit_number');

    expect($numbers)->toBe(['A01', 'B02', 'C03']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Pagination — _per_page bounds (validated by ShowUnitsRequest)
// ──────────────────────────────────────────────────────────────────────────────

it('respects _per_page within the allowed range', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    Unit::factory()->count(7)->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_per_page=3')
        ->assertOk();

    expect($resp->json('meta.per_page'))->toBe(3);
    expect(count($resp->json('data')))->toBe(3);
});

it('rejects _per_page below 1', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_per_page=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_per_page']);
});

it('rejects _per_page above 200', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_per_page']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters
// ──────────────────────────────────────────────────────────────────────────────

it('filters by occupancy_type', function (string $type) {
    $user   = adminUser();
    $community = makeCommunity($user);
    foreach (['owner_occupied', 'occupant_occupied', 'vacant'] as $occ) {
        Unit::factory()->count(2)->create([
            'community_id'      => $community->id,
            'organization_id'      => $user->organization_id,
            'occupancy_type' => $occ,
        ]);
    }

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . "?occupancy_type={$type}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
    foreach ($resp->json('data') as $row) {
        expect($row['occupancy_type'])->toBe($type);
    }
})->with(['owner_occupied', 'occupant_occupied', 'vacant']);

it('rejects an invalid occupancy_type filter with 422', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?occupancy_type=nope')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('filters by status', function (string $status) {
    $user   = adminUser();
    $community = makeCommunity($user);
    foreach (['active', 'suspended', 'vacated'] as $s) {
        Unit::factory()->create([
            'community_id' => $community->id,
            'organization_id' => $user->organization_id,
            'status'    => $s,
        ]);
    }

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . "?status={$status}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
    expect($resp->json('data.0.status'))->toBe($status);
})->with(['active', 'suspended', 'vacated']);

it('rejects an invalid status filter with 422', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?status=zombie')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('filters by balance=in_arrears (balance < 0)', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'balance' => -250]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'balance' => 0]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'balance' => 500]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?balance=in_arrears')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
    expect((float) $resp->json('data.0.balance'))->toBeLessThan(0);
});

it('filters by balance=clear (balance >= 0)', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'balance' => -250]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'balance' => 0]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'balance' => 500]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?balance=clear')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
});

it('rejects an invalid balance filter with 422', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?balance=red')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['balance']);
});

it('combines occupancy_type, status and balance filters', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'occupant_occupied', 'status' => 'active',    'balance' => -100]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'occupant_occupied', 'status' => 'active',    'balance' =>    0]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'owner_occupied',  'status' => 'active',    'balance' => -100]);
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'occupant_occupied', 'status' => 'suspended', 'balance' => -100]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?occupancy_type=occupant_occupied&status=active&balance=in_arrears')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sort (_sort) — UnitService overrides applySortOnQuery
// ──────────────────────────────────────────────────────────────────────────────

it('sorts by unit_number ascending', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    foreach (['C03', 'A01', 'B02'] as $n) {
        makeUnit($community, ['unit_number' => $n]);
    }

    $nums = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_sort=unit_number:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    expect($nums)->toBe(['A01', 'B02', 'C03']);
});

it('sorts by unit_number descending', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    foreach (['C03', 'A01', 'B02'] as $n) {
        makeUnit($community, ['unit_number' => $n]);
    }

    $nums = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_sort=unit_number:desc')
        ->assertOk()
        ->json('data.*.unit_number');

    expect($nums)->toBe(['C03', 'B02', 'A01']);
});

it('sorts by owner_name (left joins owners table; no-owner units still appear)', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $u1 = makeUnit($community, ['unit_number' => 'X1']);
    $u2 = makeUnit($community, ['unit_number' => 'X2']);
    $u3 = makeUnit($community, ['unit_number' => 'X3']); // no owner — must still appear via left join
    attachOwner($u1, ['full_name' => 'Bob']);
    attachOwner($u2, ['full_name' => 'Alice']);

    $nums = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_sort=owner_name:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    expect(array_search('X2', $nums))->toBeLessThan(array_search('X1', $nums));
    expect($nums)->toContain('X3');
});

it('sorts by outstanding_amount asc → most-arrears first (most negative balance)', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    makeUnit($community, ['unit_number' => 'A', 'balance' =>  100]);
    makeUnit($community, ['unit_number' => 'B', 'balance' => -300]);
    makeUnit($community, ['unit_number' => 'C', 'balance' => -500]);

    $nums = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_sort=outstanding_amount:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    expect($nums)->toBe(['C', 'B', 'A']);
});

it('rejects a malformed _sort string with 422', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_sort=' . urlencode('name);DROP TABLE units'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_sort']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range
// ──────────────────────────────────────────────────────────────────────────────

it('filters by _date_range=today', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    makeUnit($community, ['created_at' => now()]);
    makeUnit($community, ['created_at' => now()->subDays(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_date_range=today')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters by _date_range=custom with start + end', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    makeUnit($community, ['created_at' => Carbon::parse('2026-02-15')]);
    makeUnit($community, ['created_at' => Carbon::parse('2026-04-15')]);
    makeUnit($community, ['created_at' => Carbon::parse('2026-06-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_date_range=custom&_date_range_start=2026-03-01&_date_range_end=2026-05-31')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('rejects _date_range_end before _date_range_start with 422', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_date_range=custom&_date_range_start=2026-05-01&_date_range_end=2026-03-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_date_range_end']);
});

it('rejects an invalid _date_range value with 422', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_date_range=last_decade')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_date_range']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (Postgres-only)
// ──────────────────────────────────────────────────────────────────────────────

it('searches across unit_number, address, owner.full_name and owner.email', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $u      = makeUnit($community, ['unit_number' => 'CRYSTAL-1']);
    attachOwner($u, ['full_name' => 'Acme']);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_search=Crystal')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

// ──────────────────────────────────────────────────────────────────────────────
// charts payload sanity
// ──────────────────────────────────────────────────────────────────────────────

it('reports the correct occupancy chart counts', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    Unit::factory()->count(2)->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'owner_occupied']);
    Unit::factory()->count(3)->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'occupant_occupied']);
    Unit::factory()->count(1)->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'vacant']);

    $charts = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community))
        ->assertOk()
        ->json('charts');

    expect($charts['occupancy'])->toBe([
        'owner_occupied' => 2,
        'occupant_occupied' => 3,
        'vacant' => 1,
    ]);
});

it('lists in-arrears units in top_owner_arrears, sorted by arrears desc', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $a = makeUnit($community, ['unit_number' => 'A1', 'balance' => -300]);
    $b = makeUnit($community, ['unit_number' => 'B1', 'balance' => -800]);
    makeUnit($community, ['unit_number' => 'C1', 'balance' =>  400]); // not in arrears
    attachOwner($a, ['full_name' => 'Owner A']);
    attachOwner($b, ['full_name' => 'Owner B']);

    $top = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community))
        ->assertOk()
        ->json('charts.top_owner_arrears');

    expect(count($top))->toBe(2);
    expect($top[0]['unit_number'])->toBe('B1');
    expect((float) $top[0]['outstanding'])->toBe(800.0);
    expect($top[1]['unit_number'])->toBe('A1');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/communities/{community}/units/{unit}  —  show                           ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns a single unit with owner, currentOccupant, chargeConfigs and community eager-loaded', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community, ['unit_number' => 'A1', 'occupancy_type' => 'occupant_occupied']);
    attachOwner($unit, ['full_name' => 'Acme', 'email' => 'acme@x.com']);
    Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', ['community' => $community, 'unit' => $unit]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'unit_number', 'balance', 'outstanding_amount', 'unallocated_credits',
                       'owner' => ['id', 'full_name', 'email'],
                       'current_occupant' => ['id', 'full_name', 'is_active'],
                       'community' => ['id', 'name']],
        ])
        ->json();

    expect($body['data']['id'])->toBe($unit->id);
    expect($body['data']['owner']['email'])->toBe('acme@x.com');
});

it('computes outstanding_amount and unallocated_credits via subqueries on show', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);
    $owner  = attachOwner($unit);

    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    // Outstanding: 1000 unpaid invoice with 200 already partially paid → net 800
    $invoice = Invoice::factory()->create([
        'unit_id'        => $unit->id,
        'organization_id'      => $user->organization_id,
        'ledger_id' => $ledger->id,
        'invoice_number' => 'INV-001',
        'amount'         => 1000,
        'status'         => 'partially_paid',
        'billed_to_type' => 'owner',
        'billed_to_id'   => $owner->id,
    ]);
    CashbookEntry::factory()->create([
        'community_id'  => $community->id,
        'unit_id'    => $unit->id,
        'organization_id'  => $user->organization_id,
        'invoice_id' => $invoice->id,
        'date'       => now(),
        'type'       => 'credit',
        'amount'     => 200,
    ]);
    // Unallocated credit: 50
    CashbookEntry::factory()->create([
        'community_id'  => $community->id,
        'unit_id'    => $unit->id,
        'organization_id'  => $user->organization_id,
        'invoice_id' => null,
        'date'       => now(),
        'type'       => 'credit',
        'amount'     => 50,
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', ['community' => $community, 'unit' => $unit]))
        ->assertOk()
        ->json('data');

    expect((float) $data['outstanding_amount'])->toBe(800.0);
    expect((float) $data['unallocated_credits'])->toBe(50.0);
});

it('returns 404 when the unit id does not exist', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', ['community' => $community, 'unit' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/.../units/{unit}/activities                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns paginated unit activities newest first', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);

    UnitActivity::create([
        'unit_id'    => $unit->id, 'organization_id' => $user->organization_id,
        'event'      => 'older event', 'category' => 'unit',
        'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
    ]);
    UnitActivity::create([
        'unit_id'    => $unit->id, 'organization_id' => $user->organization_id,
        'event'      => 'newer event', 'category' => 'unit',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.activities', ['community' => $community, 'unit' => $unit]))
        ->assertOk()
        ->json();

    expect($body['data'])->toBeArray();
    expect($body['data'][0]['event'])->toBe('newer event');
    expect($body['data'][1]['event'])->toBe('older event');
    expect($body['meta'])->toHaveKeys(['total', 'current_page', 'last_page', 'per_page']);
});

it('returns an empty array when the unit has no activities', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.activities', ['community' => $community, 'unit' => $unit]))
        ->assertOk()
        ->json();

    expect($body['data'])->toBe([]);
    expect($body['meta']['total'])->toBe(0);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/communities/{community}/units  —  create                               ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// Validation rules

it('rejects create without unit_number', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'occupancy_type' => 'owner_occupied',
            'owner' => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_number']);
});

it('rejects create with unit_number > 50 chars', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => str_repeat('X', 51),
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_number']);
});

it('rejects create with address > 500 chars', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'address'        => str_repeat('a', 501),
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address']);
});

it('rejects create without occupancy_type', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number' => 'A1',
            'owner'       => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('rejects create with invalid occupancy_type', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'student',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('rejects create with invalid status', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'status'         => 'demolished',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects create with negative levy_override', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'levy_override'  => -1,
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['levy_override']);
});

it('rejects create with negative rent_amount', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'rent_amount'    => -50,
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rent_amount']);
});

it('rejects create when owner array is missing entirely', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner']);
});

it('rejects create when owner.full_name is missing', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.full_name']);
});

it('rejects create when owner.email is missing or invalid', function (string $email) {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'X', 'email' => $email],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.email']);
})->with(['', 'not-an-email', '@x.com', 'has spaces@x.com']);

it('rejects create when owner.full_name > 255 chars', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => str_repeat('z', 256), 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.full_name']);
});

it('rejects create when owner.phone > 30 chars', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com', 'phone' => str_repeat('1', 31)],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.phone']);
});

// Organization payload — required_if + lease validation

it('requires occupant.full_name when occupancy=occupant_occupied', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'occupant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'occupant'         => ['email' => 'occupant@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupant.full_name']);
});

it('requires occupant.email when occupancy=occupant_occupied', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'occupant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'occupant'         => ['full_name' => 'T'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupant.email']);
});

it('rejects create when occupant.email is invalid', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'occupant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'occupant'         => ['full_name' => 'T', 'email' => 'not-email'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupant.email']);
});

it('rejects create when lease_end is on or before lease_start', function (string $start, string $end) {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'occupant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'occupant'                 => [
                'full_name'   => 'T',
                'email'       => 't@x.com',
                'lease_start' => $start,
                'lease_end'   => $end,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupant.lease_end']);
})->with([
    'end before start'  => ['2026-06-01', '2026-05-01'],
    'end same as start' => ['2026-06-01', '2026-06-01'],
]);

// Community-type rule: sectional_title disallows occupant occupancy

it('rejects creating a occupant_occupied unit in a sectional_title community', function () {
    $user   = adminUser();
    $community = makeCommunity($user, ['entity_type' => 'body_corporate']);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'occupant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'occupant'         => ['full_name' => 'T', 'email' => 't@x.com'],
        ])
        ->assertUnprocessable();

    expect($resp->json('errors'))->toHaveKey('occupancy_type');
});

it('rejects sending occupant block on a sectional_title community even with valid occupancy', function () {
    $user   = adminUser();
    $community = makeCommunity($user, ['entity_type' => 'body_corporate']);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'occupant'         => ['full_name' => 'T', 'email' => 't@x.com'],
        ])
        ->assertUnprocessable();

    expect($resp->json('errors'))->toHaveKey('occupant');
});

// Successful creates & side effects

it('creates an owner-occupied unit with an Owner record and defaults status to active', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'levy_override'  => 1500,
            'owner'          => [
                'full_name' => 'Joe Smith',
                'email'     => 'joe@example.com',
                'phone'     => '+27821234567',
                'id_number' => '8001015009087',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Created successfully')
        ->assertJsonPath('data.unit_number', 'A1')
        ->assertJsonPath('data.occupancy_type', 'owner_occupied')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.community_id', $community->id)
        ->assertJsonPath('data.organization_id', $user->organization_id);

    $unitId = $resp->json('data.id');
    $this->assertDatabaseHas('units', ['id' => $unitId, 'status' => 'active', 'levy_override' => 1500]);
    $this->assertDatabaseHas('owners', ['unit_id' => $unitId, 'full_name' => 'Joe Smith', 'email' => 'joe@example.com']);
    $this->assertDatabaseMissing('occupants', ['unit_id' => $unitId]);
});

it('creates a occupant-occupied unit with both Owner and active Occupant records', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'B2',
            'occupancy_type' => 'occupant_occupied',
            'rent_amount'    => 5000,
            'owner'          => ['full_name' => 'Landlord LLC', 'email' => 'land@x.com'],
            'occupant'                 => [
                'full_name'   => 'Jane Occupant',
                'email'       => 'jane@x.com',
                'phone'       => '+267 7 1234567',
                'lease_start' => '2026-01-01',
                'lease_end'   => '2027-01-01',
                'rent_amount' => 5000,
            ],
        ])
        ->assertOk();

    $unitId = $resp->json('data.id');
    $this->assertDatabaseHas('owners', ['unit_id' => $unitId, 'full_name' => 'Landlord LLC']);
    $this->assertDatabaseHas('occupants', [
        'unit_id'   => $unitId,
        'full_name' => 'Jane Occupant',
        'email'     => 'jane@x.com',
        'is_active' => true,
    ]);
});

it('does not create a Occupant on a vacant or owner_occupied unit even if a occupant block is sent', function (string $occ) {
    $user   = adminUser();
    $community = makeCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => $occ,
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'occupant'         => ['full_name' => 'IGNORED', 'email' => 'i@x.com'],
        ])
        ->assertOk();

    $this->assertDatabaseMissing('occupants', ['unit_id' => $resp->json('data.id')]);
})->with(['owner_occupied', 'vacant']);

it('forces organization_id and community_id from auth + route — clients cannot spoof them', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $other  = createOrganization();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'organization_id'      => $other->id,                                   // ← spoof attempt
            'community_id'      => '00000000-0000-0000-0000-000000000000',       // ← spoof attempt
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertOk();

    expect($resp->json('data.organization_id'))->toBe($user->organization_id);
    expect($resp->json('data.community_id'))->toBe($community->id);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/.../units/{unit}  —  update                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('updates a unit with a partial payload — untouched fields remain unchanged', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community, ['unit_number' => 'A1', 'levy_override' => 100, 'rent_amount' => 5000]);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'levy_override' => 250,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully');

    $this->assertDatabaseHas('units', [
        'id'            => $unit->id,
        'unit_number'   => 'A1',
        'levy_override' => 250,
        'rent_amount'   => 5000,
    ]);
});

it('updates owner details when owner.* is provided', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);
    attachOwner($unit, ['full_name' => 'Old Name', 'email' => 'old@x.com']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'owner' => ['full_name' => 'New Name', 'email' => 'new@x.com'],
        ])
        ->assertOk();

    $this->assertDatabaseHas('owners', ['unit_id' => $unit->id, 'full_name' => 'New Name', 'email' => 'new@x.com']);
});

it('updates the active occupant when occupant.* is provided', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community, ['occupancy_type' => 'occupant_occupied']);
    attachOwner($unit);
    Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Old Occupant',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'occupant'         => ['full_name' => 'New Occupant'],
        ])
        ->assertOk();

    $this->assertDatabaseHas('occupants', ['unit_id' => $unit->id, 'full_name' => 'New Occupant', 'is_active' => true]);
});

it('creates a new active occupant and flips occupancy to occupant_occupied when none exists', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community, ['occupancy_type' => 'owner_occupied']);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'occupant'         => [
                'full_name'   => 'Brand New',
                'email'       => 'new@occupant.com',
                'lease_start' => '2026-01-01',
                'lease_end'   => '2027-01-01',
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('units', ['id' => $unit->id, 'occupancy_type' => 'occupant_occupied']);
    $this->assertDatabaseHas('occupants', ['unit_id' => $unit->id, 'full_name' => 'Brand New', 'is_active' => true]);
});

it('writes a UnitActivity entry per category that actually changed (sharing one batch_id)', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community, ['unit_number' => 'A1', 'rent_amount' => 5000]);
    attachOwner($unit, ['full_name' => 'Owner A']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'rent_amount' => 6000,
            'owner'       => ['full_name' => 'Owner B'],
        ])
        ->assertOk();

    $logs = UnitActivity::where('unit_id', $unit->id)->orderBy('category')->get();

    expect($logs)->toHaveCount(2);
    expect($logs->pluck('category')->all())->toBe(['owner', 'unit']);
    expect($logs->pluck('batch_id')->unique()->count())->toBe(1);
});

it('rejects update with invalid occupancy_type', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'occupancy_type' => 'tornado',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('rejects update with invalid status', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'status' => 'imaginary',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects update with invalid owner.email', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'owner' => ['email' => 'not-email'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.email']);
});

it('rejects update where lease_end is before lease_start', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community, ['occupancy_type' => 'occupant_occupied']);
    attachOwner($unit);
    Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'occupant'         => ['lease_start' => '2026-06-01', 'lease_end' => '2026-05-01'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupant.lease_end']);
});

it('rejects update switching to occupant_occupied on a sectional_title community', function () {
    $user   = adminUser();
    $community = makeCommunity($user, ['entity_type' => 'body_corporate']);
    $unit   = makeUnit($community, ['occupancy_type' => 'owner_occupied']);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'occupancy_type' => 'occupant_occupied',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/.../units/{unit}  —  single                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('deletes a single unit and returns the success payload', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit', ['community' => $community, 'unit' => $unit]))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Unit deleted']);

    $this->assertDatabaseMissing('units', ['id' => $unit->id]);
});

it('returns 404 when deleting an unknown unit id', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit', ['community' => $community, 'unit' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/.../units  —  bulk                                            ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('bulk deletes own-community units and pluralises the message', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $units  = Unit::factory()->count(3)->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $community), ['unit_ids' => $units->pluck('id')->all()])
        ->assertOk()
        ->assertJson(['message' => '3 Units deleted']);

    foreach ($units as $u) {
        $this->assertDatabaseMissing('units', ['id' => $u->id]);
    }
});

it('uses singular label for one unit', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $community), ['unit_ids' => [$unit->id]])
        ->assertOk()
        ->assertJson(['message' => '1 Unit deleted']);
});

it('only deletes units that belong to the route community', function () {
    $user    = adminUser();
    $communityA = makeCommunity($user);
    $communityB = makeCommunity($user);
    $unitInA = makeUnit($communityA);
    $unitInB = makeUnit($communityB);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $communityA), ['unit_ids' => [$unitInA->id, $unitInB->id]])
        ->assertOk()
        ->assertJson(['message' => '1 Unit deleted']);

    $this->assertDatabaseMissing('units', ['id' => $unitInA->id]);
    $this->assertDatabaseHas('units', ['id' => $unitInB->id]);
});

it('returns 500 when none of the supplied unit_ids belong to the route community', function () {
    $user    = adminUser();
    $communityA = makeCommunity($user);
    $communityB = makeCommunity($user);
    $unitInB = makeUnit($communityB);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $communityA), ['unit_ids' => [$unitInB->id]])
        ->assertStatus(500);

    $this->assertDatabaseHas('units', ['id' => $unitInB->id]);
});

it('rejects non-uuid values in unit_ids', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $unit   = makeUnit($community);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $community), ['unit_ids' => [$unit->id, 'not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_ids.1']);
});

it('rejects unit_ids that is not an array', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $community), ['unit_ids' => 'a-single-id'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_ids']);
});

it('returns 403 when bulk delete unit_ids is missing or empty (policy guard)', function (array $payload) {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $community), $payload)
        ->assertForbidden();
})->with([
    'missing' => [[]],
    'empty'   => [['unit_ids' => []]],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/.../units/bulk-import  —  rows                                  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('upserts rows (updates matching units) and reports per-row errors that escape request-level validation', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $existing = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id, 'unit_number' => 'A01']);
    attachOwner($existing, ['full_name' => 'Old Owner', 'email' => 'old@x.com']);

    // WeConnectU "Bulk edit Units" is an upsert: the case-insensitive `a01` row matches the
    // existing A01 unit and UPDATES its owner rather than being skipped as a duplicate.
    // Per-row service errors still fire for things the request schema doesn't enforce (email format).
    $rows = [
        ['unit_number' => 'B02', 'occupancy_type' => 'owner_occupied',  'owner_full_name' => 'Owner B', 'owner_email' => 'b@x.com'],
        ['unit_number' => 'a01', 'occupancy_type' => 'owner_occupied',  'owner_full_name' => 'New Owner', 'owner_email' => 'new@x.com'],
        ['unit_number' => 'C03', 'occupancy_type' => 'occupant_occupied', 'owner_full_name' => 'Owner C', 'owner_email' => 'c@x.com',
         'occupant_full_name' => 'Occupant C', 'occupant_email' => 'not-an-email'],
        ['unit_number' => 'D04', 'occupancy_type' => 'occupant_occupied', 'owner_full_name' => 'Owner D', 'owner_email' => 'd@x.com',
         'occupant_full_name' => 'Occupant D', 'occupant_email' => 'td@x.com', 'occupant_lease_start' => '2026-01-01', 'occupant_lease_end' => '2027-01-01'],
    ];

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $community), ['rows' => $rows])
        ->assertOk()
        ->json();

    expect($body)->toHaveKeys(['imported', 'duplicates', 'error_count', 'errors', 'total', 'message']);
    expect($body['total'])->toBe(4);
    expect($body['imported'])->toBe(3);    // B02 (new) + a01 (update) + D04 (new)
    expect($body['error_count'])->toBe(1); // C03 has invalid occupant_email
    expect($body['errors'][0]['row'])->toBe(3);
    expect($body['errors'][0]['errors'])->toContain("Occupant email 'not-an-email' is invalid.");

    $this->assertDatabaseHas('units', ['community_id' => $community->id, 'unit_number' => 'B02']);
    $this->assertDatabaseHas('units', ['community_id' => $community->id, 'unit_number' => 'D04']);
    $this->assertDatabaseMissing('units', ['community_id' => $community->id, 'unit_number' => 'C03']);
    // The a01 row updated the existing A01 owner rather than creating a new unit.
    $this->assertDatabaseHas('owners', ['unit_id' => $existing->id, 'email' => 'new@x.com']);
    $this->assertDatabaseHas('owners', ['unit_id' => Unit::where('unit_number', 'B02')->first()->id, 'email' => 'b@x.com']);
    $this->assertDatabaseHas('occupants', ['unit_id' => Unit::where('unit_number', 'D04')->first()->id, 'email' => 'td@x.com', 'is_active' => true]);
});

it('rejects bulk import when rows is missing', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $community), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rows']);
});

it('rejects bulk import when rows is an empty array', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $community), ['rows' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rows']);
});

it('reports a per-row error when a bulk import row is missing the unit number', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $community), [
            'rows' => [
                ['occupancy_type' => 'owner_occupied', 'owner_full_name' => 'X', 'owner_email' => 'x@x.com'],
            ],
        ])
        ->assertOk()
        ->json();

    expect($body['imported'])->toBe(0);
    expect($body['error_count'])->toBe(1);
    expect($body['errors'][0]['errors'])->toContain('Unit number is required.');
});

it('reports a per-row error when a bulk import row has an invalid occupancy_type', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $community), [
            'rows' => [
                ['unit_number' => 'A1', 'occupancy_type' => 'rented', 'owner_full_name' => 'X', 'owner_email' => 'x@x.com'],
            ],
        ])
        ->assertOk()
        ->json();

    expect($body['imported'])->toBe(0);
    expect($body['error_count'])->toBe(1);
    expect($body['errors'][0]['errors'])->toContain('Occupancy type must be owner_occupied, occupant_occupied, or vacant.');
});

it('rejects bulk import row with invalid owner_email', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $community), [
            'rows' => [
                ['unit_number' => 'A1', 'occupancy_type' => 'owner_occupied', 'owner_full_name' => 'X', 'owner_email' => 'not-email'],
            ],
        ])
        ->assertOk()
        ->json();

    expect($body['error_count'])->toBe(1);
    expect($body['errors'][0]['errors'])->toContain("Owner email 'not-email' is not a valid email address.");
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Bulk-import template & file parse                                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('downloads a CSV template by default', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->get(route('api.v1.bulk.import.template', $community))
        ->assertOk();

    expect($resp->headers->get('Content-Type'))->toContain('text/csv');
    expect($resp->headers->get('Content-Disposition'))->toContain('units-import-template.csv');
    expect($resp->getContent())->toContain('unit_number');
    expect($resp->getContent())->toContain('occupancy_type');
    expect($resp->getContent())->toContain('owner_full_name,owner_id_number,owner_email');
});

it('parses an uploaded CSV file into columns + rows', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $csv = "unit_number,occupancy_type,owner_full_name,owner_email\n"
         . "A01,owner_occupied,John,john@x.com\n"
         . "A02,vacant,Jane,jane@x.com\n";

    $file = UploadedFile::fake()->createWithContent('input.csv', $csv);

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.parse', $community), ['file' => $file])
        ->assertOk()
        ->json();

    expect($body['columns'])->toBe(['unit_number', 'occupancy_type', 'owner_full_name', 'owner_email']);
    expect($body['total_rows'])->toBe(2);
    expect($body['rows'][0])->toBe([
        'unit_number'     => 'A01',
        'occupancy_type'  => 'owner_occupied',
        'owner_full_name' => 'John',
        'owner_email'     => 'john@x.com',
    ]);
    expect($body['rows'][1]['unit_number'])->toBe('A02');
});

it('rejects bulk-import-parse when no file is provided', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.parse', $community), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('rejects bulk-import-parse when file is the wrong mime type', function () {
    $user   = adminUser();
    $community = makeCommunity($user);

    $file = UploadedFile::fake()->create('badtype.txt', 10, 'text/plain');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.parse', $community), ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Export                                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('exports units as CSV with the expected headings', function () {
    $user   = adminUser();
    $community = makeCommunity($user, ['name' => 'Crystal Mews']);
    $unit   = makeUnit($community, ['unit_number' => 'A01', 'occupancy_type' => 'owner_occupied', 'balance' => -250]);
    attachOwner($unit, ['full_name' => 'Joe', 'email' => 'joe@x.com']);

    $resp = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.units', $community) . '?_format=csv');

    $resp->assertOk();
    expect($resp->headers->get('Content-Type'))->toContain('text/csv');
    // WeConnectU-style filename: "unit export-<name> <entity type>-.csv".
    expect($resp->headers->get('Content-Disposition'))->toContain('unit export-crystal mews residential rental-.csv');

    // streamDownload responds with a StreamedResponse — capture the streamed body.
    $csv = $resp->streamedContent();
    // Header row matches WeConnectU's exact 38-column layout.
    expect($csv)->toContain('Block,"Section / Erf No","Unit No","Door No"');
    expect($csv)->toContain('"Owner / Contact Name","ID / Passport","Email Address","Cell Number"');
    expect($csv)->toContain('"Customer Code","Customer Name","UNIT ID","OWNER ID"');
    // Data: unit number + owner details present.
    expect($csv)->toContain('A01');
    expect($csv)->toContain('Joe');
    expect($csv)->toContain('joe@x.com');
});

it('respects export filters (occupancy_type narrows the dataset)', function () {
    $user   = adminUser();
    $community = makeCommunity($user);
    $a = makeUnit($community, ['unit_number' => 'A1', 'occupancy_type' => 'owner_occupied']);
    $b = makeUnit($community, ['unit_number' => 'B1', 'occupancy_type' => 'occupant_occupied']);
    attachOwner($a);
    attachOwner($b);

    $csv = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.units', $community) . '?_format=csv&occupancy_type=owner_occupied')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('A1');
    expect($csv)->not->toContain('B1');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Cross-community / cross-occupant isolation                                    ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('cross-community unit show returns 404', function () {
    $user   = adminUser();
    $right  = makeCommunity($user);
    $wrong  = makeCommunity($user);
    $unit   = makeUnit($right);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', ['community' => $wrong, 'unit' => $unit]))
        ->assertNotFound();
});

it('cross-occupant unit listing returns 404', function () {
    $user        = adminUser();
    $otherCommunity = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $otherCommunity))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ WeConnectU fields + Customer Code                                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('persists the WeConnectU unit and owner fields on create', function () {
    $user      = adminUser();
    $community = makeCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $community), [
            'unit_number'    => '1',
            'block_number'   => 'B1',
            'section'        => 'S1',
            'door_number'    => '1',
            'occupancy_type' => 'owner_occupied',
            'billing_pdf'    => true,
            'owner'          => [
                'full_name'         => 'A Tlowana',
                'email'             => 'atlowana@gmail.com',
                'phone'             => '0721111111',
                'landline'          => '0111111111',
                'id_number'         => '8001015009087',
                'entity_type'       => 'individual',
                'contact2_name'     => 'B Tlowana',
                'contact2_email'    => 'btlowana@gmail.com',
                'contact2_phone'    => '0722222222',
                'contact2_landline' => '0112222222',
            ],
        ])
        ->assertOk();

    $unit = Unit::where('community_id', $community->id)->where('unit_number', '1')->firstOrFail();
    expect($unit->block_number)->toBe('B1');
    expect($unit->door_number)->toBe('1');
    expect((bool) $unit->billing_pdf)->toBeTrue();
    expect($unit->customer_code)->toBe('ATL001-D1');

    $owner = $unit->owner;
    expect($owner->landline)->toBe('0111111111');
    expect($owner->entity_type->value)->toBe('individual');
    expect($owner->contact2_name)->toBe('B Tlowana');
    expect($owner->contact2_email)->toBe('btlowana@gmail.com');
});

it('keeps the customer code stable across an ordinary owner edit', function () {
    $user      = adminUser();
    $community = makeCommunity($user);
    $unit      = makeUnit($community, ['unit_number' => '1', 'door_number' => '1', 'customer_code' => 'ATL001-D1']);
    attachOwner($unit, ['full_name' => 'A Tlowana', 'email' => 'atlowana@gmail.com']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'owner' => ['phone' => '0729999999'],
        ])
        ->assertOk();

    expect($unit->fresh()->customer_code)->toBe('ATL001-D1');
});

it('regenerates the customer code on an explicit ownership change', function () {
    $user      = adminUser();
    $community = makeCommunity($user);
    $unit      = makeUnit($community, ['unit_number' => '1', 'door_number' => '1', 'customer_code' => 'ATL001-D1']);
    attachOwner($unit, ['full_name' => 'A Tlowana', 'email' => 'atlowana@gmail.com']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'ownership_change' => true,
            'owner'            => ['full_name' => 'Diale Mahlatse', 'email' => 'diale@gmail.com'],
        ])
        ->assertOk();

    expect($unit->fresh()->customer_code)->toBe('DIA001-D1');
});

it('matches units by customer_code in search', function () {
    $user      = adminUser();
    $community = makeCommunity($user);
    $match     = makeUnit($community, ['unit_number' => '1', 'customer_code' => 'ATL001-D1']);
    $other     = makeUnit($community, ['unit_number' => '2', 'customer_code' => 'DIA001-D2']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_search=ATL001')
        ->assertOk();

    $ids = collect($resp->json('data'))->pluck('id');
    expect($ids)->toContain($match->id);
    expect($ids)->not->toContain($other->id);
});

it('sorts units by customer_code', function () {
    $user      = adminUser();
    $community = makeCommunity($user);
    makeUnit($community, ['unit_number' => '2', 'customer_code' => 'ZZZ001-D2']);
    makeUnit($community, ['unit_number' => '1', 'customer_code' => 'AAA001-D1']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $community) . '?_sort=customer_code:asc')
        ->assertOk();

    $codes = collect($resp->json('data'))->pluck('customer_code')->filter()->values();
    expect($codes->first())->toBe('AAA001-D1');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Bulk edit round-trip + Occupants export (WeConnectU parity)              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('bulk-imports using the WeConnectU export headers and updates existing units', function () {
    $user      = adminUser();
    $community = makeCommunity($user);
    $unit      = makeUnit($community, ['unit_number' => 'A01', 'customer_code' => 'OLD001-D1']);
    attachOwner($unit, ['full_name' => 'Old Owner', 'email' => 'old@x.com']);

    // A row keyed by export headers (as produced by "Download existing unit file here").
    $rows = [[
        'Unit No'              => 'A01',
        'Customer Code'        => 'OLD001-D1',
        'Owner / Contact Name' => 'Brand New Owner',
        'Email Address'        => 'brandnew@x.com',
        'Cell Number'          => '0821234567',
        'Section / Erf No'     => 'S9',
        'Door No'              => 'D9',
    ]];

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $community), ['rows' => $rows])
        ->assertOk()
        ->json();

    expect($body['imported'])->toBe(1);
    $unit->refresh();
    expect($unit->section)->toBe('S9');
    expect($unit->owner->full_name)->toBe('Brand New Owner');
    expect($unit->owner->email)->toBe('brandnew@x.com');
});

it('exports occupants with the WeConnectU filename and headers', function () {
    $user      = adminUser();
    $community = makeCommunity($user, ['name' => 'Oakhurst BC', 'entity_type' => 'body_corporate']);
    $unit      = makeUnit($community, ['unit_number' => 'A01', 'occupancy_type' => 'occupant_occupied']);
    attachOwner($unit, ['full_name' => 'Owner A']);
    Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $community->organization_id, 'full_name' => 'Occ A', 'email' => 'occ@x.com', 'is_active' => true]);

    $resp = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.occupants', $community) . '?_format=csv');

    $resp->assertOk();
    expect($resp->headers->get('Content-Disposition'))->toContain('units occupants export-oakhurst bc body corporate-.csv');
    $csv = $resp->streamedContent();
    expect($csv)->toContain('"Unit No","Door No","Customer Code"');
    expect($csv)->toContain('"Occupant Name","Occupant Email","Occupant Cell Number"');
    expect($csv)->toContain('Occ A');
    expect($csv)->toContain('occ@x.com');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Development unit toggle (WeConnectU wrench)                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('toggles a unit development status on and off', function () {
    $user      = adminUser();
    $community = makeCommunity($user);
    $unit      = makeUnit($community, ['is_development' => false]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.toggle.development', ['community' => $community, 'unit' => $unit]))
        ->assertOk()
        ->assertJson(['is_development' => true]);

    expect($unit->fresh()->is_development)->toBeTrue();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.toggle.development', ['community' => $community, 'unit' => $unit]))
        ->assertOk()
        ->assertJson(['is_development' => false]);

    expect($unit->fresh()->is_development)->toBeFalse();
});

it('returns 401 toggling development when unauthenticated', function () {
    $this->postJson(route('api.v1.toggle.development', ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']))
        ->assertUnauthorized();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unit-detail edit modals (notes/contacts + customer/banking)             ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('updates unit notes and additional contact emails', function () {
    $user      = adminUser();
    $community = makeCommunity($user);
    $unit      = makeUnit($community);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'unit_notes'         => 'Keys at reception',
            'rental_agent_email' => 'agent@x.com',
            'attorney_email'     => 'attorney@x.com',
            'bondholder_email'   => 'bond@x.com',
        ])
        ->assertOk();

    $unit->refresh();
    expect($unit->unit_notes)->toBe('Keys at reception');
    expect($unit->rental_agent_email)->toBe('agent@x.com');
    expect($unit->attorney_email)->toBe('attorney@x.com');
    expect($unit->bondholder_email)->toBe('bond@x.com');
});

it('updates customer profile + address + banking on the owner', function () {
    $user      = adminUser();
    $community = makeCommunity($user);
    $unit      = makeUnit($community);
    attachOwner($unit, ['full_name' => 'A Owner', 'email' => 'a@x.com']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), [
            'owner' => [
                'customer_type' => 'Individual',
                'vat_no'        => 'VAT123',
                'payment_type'  => 'Debit Order',
                'town'          => 'Gaborone',
                'postal_code'   => '0000',
                'account_holder'=> 'A Owner',
                'bank_name'     => 'ABSA',
                'account_type'  => 'Current',
                'account_number'=> '1234567890',
                'branch_code'   => '632005',
                'notes'         => 'Prefers email',
            ],
        ])
        ->assertOk();

    $owner = $unit->fresh()->owner;
    expect($owner->customer_type)->toBe('Individual');
    expect($owner->payment_type)->toBe('Debit Order');
    expect($owner->bank_name)->toBe('ABSA');
    expect($owner->account_number)->toBe('1234567890');
    expect($owner->town)->toBe('Gaborone');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Multi-owner, collection status, notes, statement                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('sets a unit collection status', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['community' => $community, 'unit' => $unit]), ['collection_status' => 'handed_over'])
        ->assertOk();
    expect($unit->fresh()->collection_status->value)->toBe('handed_over');
});

it('adds an additional owner and lists both owners', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community);
    attachOwner($unit, ['full_name' => 'Primary Owner', 'email' => 'primary@x.com', 'is_primary' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.add.unit.owner', ['community' => $community, 'unit' => $unit]), ['full_name' => 'Co Owner', 'email' => 'co@x.com'])
        ->assertOk();

    expect($unit->fresh()->owners()->count())->toBe(2);
    // Primary stays primary; new owner is a co-owner.
    expect($unit->fresh()->owner->full_name)->toBe('Primary Owner');
    expect(\App\Models\Owner::where('unit_id', $unit->id)->where('email', 'co@x.com')->first()->is_primary)->toBeFalse();
});

it('refuses to delete the last owner but allows deleting a co-owner', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community);
    $primary = attachOwner($unit, ['is_primary' => true]);

    // Only one owner → cannot delete.
    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.owner', ['community' => $community, 'unit' => $unit, 'owner' => $primary]))
        ->assertStatus(500);

    $co = attachOwner($unit, ['full_name' => 'Co', 'email' => 'co2@x.com', 'is_primary' => false]);
    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.owner', ['community' => $community, 'unit' => $unit, 'owner' => $co]))
        ->assertOk();
    expect($unit->fresh()->owners()->count())->toBe(1);
});

it('adds a collection note', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.add.collection.note', ['community' => $community, 'unit' => $unit]), ['note' => 'Called owner'])
        ->assertOk();
    expect(\App\Models\UnitCollectionNote::where('unit_id', $unit->id)->where('note', 'Called owner')->exists())->toBeTrue();
});

it('downloads a customer statement with a balance b/f and totals', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community, ['customer_code' => 'ABC001-D1']); attachOwner($unit, ['full_name' => 'Joe']);

    $csv = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.statement', ['community' => $community, 'unit' => $unit]) . '?_format=csv&from=2026-06-01&to=2026-08-27')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Date,Source,Description,Debit,Credit,Balance');
    expect($csv)->toContain('Balance b/f');
    expect($csv)->toContain('Totals');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Communication log                                                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('sends a unit e-mail and logs it to the communication log', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit, ['email' => 'owner@x.com']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.send.communication', ['community' => $community, 'unit' => $unit]), [
            'recipient_email' => 'owner@x.com', 'recipient_name' => 'Owner', 'subject' => 'Hello', 'body' => '<p>Hi there</p>',
        ])
        ->assertOk();

    $this->assertDatabaseHas('unit_communications', ['unit_id' => $unit->id, 'recipient_email' => 'owner@x.com', 'subject' => 'Hello']);
});

it('lists the communication log paginated', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    \App\Models\UnitCommunication::create(['unit_id' => $unit->id, 'organization_id' => $community->organization_id, 'subject' => 'S1', 'recipient_email' => 'a@x.com']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.communications', ['community' => $community, 'unit' => $unit]))
        ->assertOk();
    expect(collect($resp->json('data'))->pluck('subject'))->toContain('S1');
});

it('resends a communication creating a new log entry', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $c = \App\Models\UnitCommunication::create(['unit_id' => $unit->id, 'organization_id' => $community->organization_id, 'subject' => 'Resend me', 'recipient_email' => 'a@x.com', 'body' => 'x']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.communication', ['community' => $community, 'unit' => $unit, 'communication' => $c]))
        ->assertOk();

    expect(\App\Models\UnitCommunication::where('unit_id', $unit->id)->where('subject', 'Resend me')->count())->toBe(2);
});

it('rejects sending a communication without a subject', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.send.communication', ['community' => $community, 'unit' => $unit]), ['recipient_email' => 'a@x.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Offences                                                                 ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('creates an offence with rules and lists it', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.offence', ['community' => $community, 'unit' => $unit]), [
            'status' => 'warning', 'issued_date' => '2026-05-19',
            'rules' => [['rule' => 'Prescribed Conduct Rule 7', 'clause' => 'Rule 7(1)'], ['rule' => 'Prescribed Conduct Rule 7', 'clause' => 'Rule 7(2)']],
        ])
        ->assertOk();

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.offences', ['community' => $community, 'unit' => $unit]))
        ->assertOk();
    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.rules'))->toHaveCount(2);
    expect($resp->json('data.0.status'))->toBe('warning');
});

it('updates an offence status', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $o = \App\Models\UnitOffence::create(['unit_id' => $unit->id, 'organization_id' => $community->organization_id, 'status' => 'warning']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.offence', ['community' => $community, 'unit' => $unit, 'offence' => $o]), ['status' => 'fine'])
        ->assertOk();
    expect($o->fresh()->status->value)->toBe('fine');
});

it('deletes an offence', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $o = \App\Models\UnitOffence::create(['unit_id' => $unit->id, 'organization_id' => $community->organization_id, 'status' => 'warning']);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.offence', ['community' => $community, 'unit' => $unit, 'offence' => $o]))
        ->assertOk();
    expect(\App\Models\UnitOffence::find($o->id))->toBeNull();
});

it('rejects an offence with an invalid status', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.offence', ['community' => $community, 'unit' => $unit]), ['status' => 'nonsense'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Tasks                                                                    ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('creates a task with a generated code and lists it', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.task', ['community' => $community, 'unit' => $unit]), [
            'title' => 'Unit 1 - Faulty geyser', 'category' => 'Insurance Claims', 'task_type' => 'Geyser Repair',
            'area' => 'Unit', 'assignee_name' => 'Tahira Ogle', 'due_date' => '2026-01-24',
            'description' => 'The owner reported a faulty geyser.',
        ])
        ->assertOk();

    expect($resp->json('data.code'))->toMatch('/^[A-Z]{3}-\d{3}$/');
    expect($resp->json('data.title'))->toBe('Unit 1 - Faulty geyser');

    $list = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.tasks', ['community' => $community, 'unit' => $unit]))
        ->assertOk();
    expect($list->json('data'))->toHaveCount(1);
    expect($list->json('data.0.category'))->toBe('Insurance Claims');
});

it('updates a task status and logs a status-change update', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $task = \App\Models\UnitTask::create([
        'title' => 'Leak', 'unit_id' => $unit->id, 'community_id' => $community->id,
        'organization_id' => $community->organization_id, 'status' => 'open',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.task', ['community' => $community, 'unit' => $unit, 'task' => $task]), ['status' => 'complete'])
        ->assertOk();

    expect($task->fresh()->status->value)->toBe('complete');
    expect(\App\Models\UnitTaskUpdate::where('unit_task_id', $task->id)->where('event', 'Status changed to: Complete')->exists())->toBeTrue();
});

it('adds feedback to a task', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $task = \App\Models\UnitTask::create([
        'title' => 'Leak', 'unit_id' => $unit->id, 'community_id' => $community->id,
        'organization_id' => $community->organization_id, 'status' => 'open',
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.add.task.update', ['community' => $community, 'unit' => $unit, 'task' => $task]), ['feedback' => 'Contractor booked.'])
        ->assertOk();

    expect(\App\Models\UnitTaskUpdate::where('unit_task_id', $task->id)->where('feedback', 'Contractor booked.')->exists())->toBeTrue();
});

it('deletes a task', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $task = \App\Models\UnitTask::create([
        'title' => 'Leak', 'unit_id' => $unit->id, 'community_id' => $community->id,
        'organization_id' => $community->organization_id, 'status' => 'open',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.task', ['community' => $community, 'unit' => $unit, 'task' => $task]))
        ->assertOk();
    expect(\App\Models\UnitTask::find($task->id))->toBeNull();
});

it('rejects a task with an invalid status', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.task', ['community' => $community, 'unit' => $unit]), ['title' => 'X', 'status' => 'nonsense'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('requires a title to create a task', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.task', ['community' => $community, 'unit' => $unit]), ['category' => 'Admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Documents                                                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('uploads a customer document and lists it', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.document', ['community' => $community, 'unit' => $unit]), [
            'name'     => 'Lease Agreement',
            'document' => \Illuminate\Http\Testing\File::create('lease.pdf', 20),
        ])
        ->assertOk();

    expect($resp->json('data.name'))->toBe('Lease Agreement');
    expect($resp->json('data.download_url'))->not->toBeNull();

    $list = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.documents', ['community' => $community, 'unit' => $unit]))
        ->assertOk();
    expect($list->json('data'))->toHaveCount(1);
});

it('defaults the document name to the file name when omitted', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.document', ['community' => $community, 'unit' => $unit]), [
            'document' => \Illuminate\Http\Testing\File::create('statement.pdf', 10),
        ])
        ->assertOk();

    expect($resp->json('data.name'))->toBe('statement.pdf');
});

it('requires a file to upload a document', function () {
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.document', ['community' => $community, 'unit' => $unit]), ['name' => 'X'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['document']);
});

it('deletes a document', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $user = adminUser(); $community = makeCommunity($user); $unit = makeUnit($community); attachOwner($unit);

    $up = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.document', ['community' => $community, 'unit' => $unit]), [
            'document' => \Illuminate\Http\Testing\File::create('doc.pdf', 5),
        ])
        ->assertOk();

    $id = $up->json('data.id');

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.document', ['community' => $community, 'unit' => $unit, 'document' => $id]))
        ->assertOk();
    expect(\App\Models\UnitDocument::find($id))->toBeNull();
});
