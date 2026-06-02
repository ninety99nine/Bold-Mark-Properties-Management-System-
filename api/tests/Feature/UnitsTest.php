<?php

use App\Models\CashbookEntry;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeEstate(\App\Models\User $user, array $overrides = []): Estate
{
    return Estate::factory()->create(array_merge([
        'organization_id'           => $user->organization_id,
        'type'                => 'residential_rental',
        'admin_fund_amount' => 1000,
    ], $overrides));
}

function makeUnit(Estate $estate, array $overrides = []): Unit
{
    return Unit::factory()->create(array_merge([
        'estate_id' => $estate->id,
        'organization_id' => $estate->organization_id,
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
    ['get',    'api.v1.show.units',           ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.export.units',         ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.create.unit',          ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.units',         ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.bulk.import.template', ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.bulk.import.parse',    ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.bulk.import.units',    ['estate' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.show.unit',            ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.unit',          ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.unit',          ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.show.unit.activities', ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/estates/{estate}/units  —  index                                 ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Pagination, scoping, charts payload
// ──────────────────────────────────────────────────────────────────────────────

it('returns the paginator structure plus a charts payload', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    Unit::factory()->count(3)->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta'  => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            'charts' => [
                'occupancy'           => ['owner_occupied', 'tenant_occupied', 'vacant'],
                'invoice_status'      => ['paid', 'overdue', 'partial'],
                'top_owner_arrears',
                'tenant_lease_expiry' => ['expired', 'this_month', 'next_month', 'in_3_months', 'beyond'],
                'top_tenant_arrears',
            ],
        ]);

    expect($resp->json('meta.total'))->toBe(3);
});

it('only returns units belonging to the requested estate', function () {
    $user = adminUser();
    $a    = makeEstate($user);
    $b    = makeEstate($user);
    Unit::factory()->count(2)->create(['estate_id' => $a->id, 'organization_id' => $user->organization_id]);
    Unit::factory()->count(5)->create(['estate_id' => $b->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $a))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
    foreach ($resp->json('data') as $row) {
        expect($row['estate_id'])->toBe($a->id);
    }
});

it('returns zero counts and empty arrays in charts when the estate has no units', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate))
        ->assertOk();

    expect($resp->json('data'))->toBe([]);
    expect($resp->json('charts.occupancy'))->toBe(['owner_occupied' => 0, 'tenant_occupied' => 0, 'vacant' => 0]);
    expect($resp->json('charts.top_owner_arrears'))->toBe([]);
    expect($resp->json('charts.top_tenant_arrears'))->toBe([]);
});

it('exposes every field the EstateDetailPage table reads', function () {
    $user   = adminUser();
    $estate = makeEstate($user, ['admin_fund_amount' => 1500]);

    $unit = makeUnit($estate, [
        'unit_number'    => 'A01',
        'occupancy_type' => 'tenant_occupied',
        'rent_amount'    => 7500,
        'levy_override'  => 2000,
        'balance'        => -500,
    ]);
    attachOwner($unit, ['full_name' => 'Acme Holdings', 'email' => 'acme@example.com']);
    Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Jane Doe',
        'email'     => 'jane@example.com',
    ]);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate))
        ->assertOk()
        ->json('data.0');

    expect($row)->toHaveKeys([
        'id', 'unit_number', 'occupancy_type', 'balance',
        'outstanding_amount', 'effective_levy_amount', 'rent_amount',
        'owner', 'current_tenant', 'total_tenants_count',
    ]);
    expect($row['unit_number'])->toBe('A01');
    expect($row['occupancy_type'])->toBe('tenant_occupied');
    expect((float) $row['balance'])->toBe(-500.0);
    expect((float) $row['rent_amount'])->toBe(7500.0);
    expect((float) $row['effective_levy_amount'])->toBe(2000.0);
    expect($row['owner']['full_name'])->toBe('Acme Holdings');
    expect($row['current_tenant']['full_name'])->toBe('Jane Doe');
    expect($row['total_tenants_count'])->toBe(1);
});

it('falls back to estate admin_fund_amount when no levy_override is set', function () {
    $user   = adminUser();
    $estate = makeEstate($user, ['admin_fund_amount' => 1500]);
    $unit   = makeUnit($estate, ['levy_override' => null]);
    attachOwner($unit);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate))
        ->assertOk()
        ->json('data.0');

    expect((float) $row['effective_levy_amount'])->toBe(1500.0);
});

it('orders units by unit_number ascending by default', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    foreach (['C03', 'A01', 'B02'] as $n) {
        makeUnit($estate, ['unit_number' => $n]);
    }

    $numbers = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate))
        ->assertOk()
        ->json('data.*.unit_number');

    expect($numbers)->toBe(['A01', 'B02', 'C03']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Pagination — _per_page bounds (validated by ShowUnitsRequest)
// ──────────────────────────────────────────────────────────────────────────────

it('respects _per_page within the allowed range', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    Unit::factory()->count(7)->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_per_page=3')
        ->assertOk();

    expect($resp->json('meta.per_page'))->toBe(3);
    expect(count($resp->json('data')))->toBe(3);
});

it('rejects _per_page below 1', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_per_page=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_per_page']);
});

it('rejects _per_page above 200', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_per_page']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters
// ──────────────────────────────────────────────────────────────────────────────

it('filters by occupancy_type', function (string $type) {
    $user   = adminUser();
    $estate = makeEstate($user);
    foreach (['owner_occupied', 'tenant_occupied', 'vacant'] as $occ) {
        Unit::factory()->count(2)->create([
            'estate_id'      => $estate->id,
            'organization_id'      => $user->organization_id,
            'occupancy_type' => $occ,
        ]);
    }

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . "?occupancy_type={$type}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
    foreach ($resp->json('data') as $row) {
        expect($row['occupancy_type'])->toBe($type);
    }
})->with(['owner_occupied', 'tenant_occupied', 'vacant']);

it('rejects an invalid occupancy_type filter with 422', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?occupancy_type=nope')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('filters by status', function (string $status) {
    $user   = adminUser();
    $estate = makeEstate($user);
    foreach (['active', 'suspended', 'vacated'] as $s) {
        Unit::factory()->create([
            'estate_id' => $estate->id,
            'organization_id' => $user->organization_id,
            'status'    => $s,
        ]);
    }

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . "?status={$status}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
    expect($resp->json('data.0.status'))->toBe($status);
})->with(['active', 'suspended', 'vacated']);

it('rejects an invalid status filter with 422', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?status=zombie')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('filters by balance=in_arrears (balance < 0)', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'balance' => -250]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'balance' => 0]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'balance' => 500]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?balance=in_arrears')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
    expect((float) $resp->json('data.0.balance'))->toBeLessThan(0);
});

it('filters by balance=clear (balance >= 0)', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'balance' => -250]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'balance' => 0]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'balance' => 500]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?balance=clear')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
});

it('rejects an invalid balance filter with 422', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?balance=red')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['balance']);
});

it('combines occupancy_type, status and balance filters', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'tenant_occupied', 'status' => 'active',    'balance' => -100]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'tenant_occupied', 'status' => 'active',    'balance' =>    0]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'owner_occupied',  'status' => 'active',    'balance' => -100]);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'tenant_occupied', 'status' => 'suspended', 'balance' => -100]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?occupancy_type=tenant_occupied&status=active&balance=in_arrears')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sort (_sort) — UnitService overrides applySortOnQuery
// ──────────────────────────────────────────────────────────────────────────────

it('sorts by unit_number ascending', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    foreach (['C03', 'A01', 'B02'] as $n) {
        makeUnit($estate, ['unit_number' => $n]);
    }

    $nums = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_sort=unit_number:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    expect($nums)->toBe(['A01', 'B02', 'C03']);
});

it('sorts by unit_number descending', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    foreach (['C03', 'A01', 'B02'] as $n) {
        makeUnit($estate, ['unit_number' => $n]);
    }

    $nums = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_sort=unit_number:desc')
        ->assertOk()
        ->json('data.*.unit_number');

    expect($nums)->toBe(['C03', 'B02', 'A01']);
});

it('sorts by owner_name (left joins owners table; no-owner units still appear)', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $u1 = makeUnit($estate, ['unit_number' => 'X1']);
    $u2 = makeUnit($estate, ['unit_number' => 'X2']);
    $u3 = makeUnit($estate, ['unit_number' => 'X3']); // no owner — must still appear via left join
    attachOwner($u1, ['full_name' => 'Bob']);
    attachOwner($u2, ['full_name' => 'Alice']);

    $nums = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_sort=owner_name:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    expect(array_search('X2', $nums))->toBeLessThan(array_search('X1', $nums));
    expect($nums)->toContain('X3');
});

it('sorts by outstanding_amount asc → most-arrears first (most negative balance)', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    makeUnit($estate, ['unit_number' => 'A', 'balance' =>  100]);
    makeUnit($estate, ['unit_number' => 'B', 'balance' => -300]);
    makeUnit($estate, ['unit_number' => 'C', 'balance' => -500]);

    $nums = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_sort=outstanding_amount:asc')
        ->assertOk()
        ->json('data.*.unit_number');

    expect($nums)->toBe(['C', 'B', 'A']);
});

it('rejects a malformed _sort string with 422', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_sort=' . urlencode('name);DROP TABLE units'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_sort']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range
// ──────────────────────────────────────────────────────────────────────────────

it('filters by _date_range=today', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    makeUnit($estate, ['created_at' => now()]);
    makeUnit($estate, ['created_at' => now()->subDays(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_date_range=today')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters by _date_range=custom with start + end', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    makeUnit($estate, ['created_at' => Carbon::parse('2026-02-15')]);
    makeUnit($estate, ['created_at' => Carbon::parse('2026-04-15')]);
    makeUnit($estate, ['created_at' => Carbon::parse('2026-06-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_date_range=custom&_date_range_start=2026-03-01&_date_range_end=2026-05-31')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('rejects _date_range_end before _date_range_start with 422', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_date_range=custom&_date_range_start=2026-05-01&_date_range_end=2026-03-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_date_range_end']);
});

it('rejects an invalid _date_range value with 422', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_date_range=last_decade')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['_date_range']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (Postgres-only)
// ──────────────────────────────────────────────────────────────────────────────

it('searches across unit_number, address, owner.full_name and owner.email', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $u      = makeUnit($estate, ['unit_number' => 'CRYSTAL-1']);
    attachOwner($u, ['full_name' => 'Acme']);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate) . '?_search=Crystal')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

// ──────────────────────────────────────────────────────────────────────────────
// charts payload sanity
// ──────────────────────────────────────────────────────────────────────────────

it('reports the correct occupancy chart counts', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    Unit::factory()->count(2)->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'owner_occupied']);
    Unit::factory()->count(3)->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'tenant_occupied']);
    Unit::factory()->count(1)->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'occupancy_type' => 'vacant']);

    $charts = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate))
        ->assertOk()
        ->json('charts');

    expect($charts['occupancy'])->toBe([
        'owner_occupied' => 2,
        'tenant_occupied' => 3,
        'vacant' => 1,
    ]);
});

it('lists in-arrears units in top_owner_arrears, sorted by arrears desc', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $a = makeUnit($estate, ['unit_number' => 'A1', 'balance' => -300]);
    $b = makeUnit($estate, ['unit_number' => 'B1', 'balance' => -800]);
    makeUnit($estate, ['unit_number' => 'C1', 'balance' =>  400]); // not in arrears
    attachOwner($a, ['full_name' => 'Owner A']);
    attachOwner($b, ['full_name' => 'Owner B']);

    $top = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $estate))
        ->assertOk()
        ->json('charts.top_owner_arrears');

    expect(count($top))->toBe(2);
    expect($top[0]['unit_number'])->toBe('B1');
    expect((float) $top[0]['outstanding'])->toBe(800.0);
    expect($top[1]['unit_number'])->toBe('A1');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/estates/{estate}/units/{unit}  —  show                           ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns a single unit with owner, currentTenant, chargeConfigs and estate eager-loaded', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate, ['unit_number' => 'A1', 'occupancy_type' => 'tenant_occupied']);
    attachOwner($unit, ['full_name' => 'Acme', 'email' => 'acme@x.com']);
    Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', ['estate' => $estate, 'unit' => $unit]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'unit_number', 'balance', 'outstanding_amount', 'unallocated_credits',
                       'owner' => ['id', 'full_name', 'email'],
                       'current_tenant' => ['id', 'full_name', 'is_active'],
                       'estate' => ['id', 'name']],
        ])
        ->json();

    expect($body['data']['id'])->toBe($unit->id);
    expect($body['data']['owner']['email'])->toBe('acme@x.com');
});

it('computes outstanding_amount and unallocated_credits via subqueries on show', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);
    $owner  = attachOwner($unit);

    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    // Outstanding: 1000 unpaid invoice with 200 already partially paid → net 800
    $invoice = Invoice::factory()->create([
        'unit_id'        => $unit->id,
        'organization_id'      => $user->organization_id,
        'charge_type_id' => $chargeType->id,
        'invoice_number' => 'INV-001',
        'amount'         => 1000,
        'status'         => 'partially_paid',
        'billed_to_type' => 'owner',
        'billed_to_id'   => $owner->id,
    ]);
    CashbookEntry::factory()->create([
        'estate_id'  => $estate->id,
        'unit_id'    => $unit->id,
        'organization_id'  => $user->organization_id,
        'invoice_id' => $invoice->id,
        'date'       => now(),
        'type'       => 'credit',
        'amount'     => 200,
    ]);
    // Unallocated credit: 50
    CashbookEntry::factory()->create([
        'estate_id'  => $estate->id,
        'unit_id'    => $unit->id,
        'organization_id'  => $user->organization_id,
        'invoice_id' => null,
        'date'       => now(),
        'type'       => 'credit',
        'amount'     => 50,
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', ['estate' => $estate, 'unit' => $unit]))
        ->assertOk()
        ->json('data');

    expect((float) $data['outstanding_amount'])->toBe(800.0);
    expect((float) $data['unallocated_credits'])->toBe(50.0);
});

it('returns 404 when the unit id does not exist', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', ['estate' => $estate, 'unit' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/.../units/{unit}/activities                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns paginated unit activities newest first', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);

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
        ->getJson(route('api.v1.show.unit.activities', ['estate' => $estate, 'unit' => $unit]))
        ->assertOk()
        ->json();

    expect($body['data'])->toBeArray();
    expect($body['data'][0]['event'])->toBe('newer event');
    expect($body['data'][1]['event'])->toBe('older event');
    expect($body['meta'])->toHaveKeys(['total', 'current_page', 'last_page', 'per_page']);
});

it('returns an empty array when the unit has no activities', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.activities', ['estate' => $estate, 'unit' => $unit]))
        ->assertOk()
        ->json();

    expect($body['data'])->toBe([]);
    expect($body['meta']['total'])->toBe(0);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/estates/{estate}/units  —  create                               ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// Validation rules

it('rejects create without unit_number', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'occupancy_type' => 'owner_occupied',
            'owner' => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_number']);
});

it('rejects create with unit_number > 50 chars', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => str_repeat('X', 51),
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_number']);
});

it('rejects create with address > 500 chars', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
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
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number' => 'A1',
            'owner'       => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('rejects create with invalid occupancy_type', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'student',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('rejects create with invalid status', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
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
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
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
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
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
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner']);
});

it('rejects create when owner.full_name is missing', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.full_name']);
});

it('rejects create when owner.email is missing or invalid', function (string $email) {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'X', 'email' => $email],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.email']);
})->with(['', 'not-an-email', '@x.com', 'has spaces@x.com']);

it('rejects create when owner.full_name > 255 chars', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => str_repeat('z', 256), 'email' => 'x@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.full_name']);
});

it('rejects create when owner.phone > 30 chars', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com', 'phone' => str_repeat('1', 31)],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.phone']);
});

// Organization payload — required_if + lease validation

it('requires tenant.full_name when occupancy=tenant_occupied', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'tenant'         => ['email' => 'tenant@x.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.full_name']);
});

it('requires tenant.email when occupancy=tenant_occupied', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'tenant'         => ['full_name' => 'T'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.email']);
});

it('rejects create when tenant.email is invalid', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'tenant'         => ['full_name' => 'T', 'email' => 'not-email'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.email']);
});

it('rejects create when lease_end is on or before lease_start', function (string $start, string $end) {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'tenant'                 => [
                'full_name'   => 'T',
                'email'       => 't@x.com',
                'lease_start' => $start,
                'lease_end'   => $end,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.lease_end']);
})->with([
    'end before start'  => ['2026-06-01', '2026-05-01'],
    'end same as start' => ['2026-06-01', '2026-06-01'],
]);

// Estate-type rule: sectional_title disallows tenant occupancy

it('rejects creating a tenant_occupied unit in a sectional_title estate', function () {
    $user   = adminUser();
    $estate = makeEstate($user, ['type' => 'sectional_title']);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'tenant_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'tenant'         => ['full_name' => 'T', 'email' => 't@x.com'],
        ])
        ->assertUnprocessable();

    expect($resp->json('errors'))->toHaveKey('occupancy_type');
});

it('rejects sending tenant block on a sectional_title estate even with valid occupancy', function () {
    $user   = adminUser();
    $estate = makeEstate($user, ['type' => 'sectional_title']);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'tenant'         => ['full_name' => 'T', 'email' => 't@x.com'],
        ])
        ->assertUnprocessable();

    expect($resp->json('errors'))->toHaveKey('tenant');
});

// Successful creates & side effects

it('creates an owner-occupied unit with an Owner record and defaults status to active', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
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
        ->assertJsonPath('data.estate_id', $estate->id)
        ->assertJsonPath('data.organization_id', $user->organization_id);

    $unitId = $resp->json('data.id');
    $this->assertDatabaseHas('units', ['id' => $unitId, 'status' => 'active', 'levy_override' => 1500]);
    $this->assertDatabaseHas('owners', ['unit_id' => $unitId, 'full_name' => 'Joe Smith', 'email' => 'joe@example.com']);
    $this->assertDatabaseMissing('tenants', ['unit_id' => $unitId]);
});

it('creates a tenant-occupied unit with both Owner and active Tenant records', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'B2',
            'occupancy_type' => 'tenant_occupied',
            'rent_amount'    => 5000,
            'owner'          => ['full_name' => 'Landlord LLC', 'email' => 'land@x.com'],
            'tenant'                 => [
                'full_name'   => 'Jane Tenant',
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
    $this->assertDatabaseHas('tenants', [
        'unit_id'   => $unitId,
        'full_name' => 'Jane Tenant',
        'email'     => 'jane@x.com',
        'is_active' => true,
    ]);
});

it('does not create a Tenant on a vacant or owner_occupied unit even if a tenant block is sent', function (string $occ) {
    $user   = adminUser();
    $estate = makeEstate($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => $occ,
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
            'tenant'         => ['full_name' => 'IGNORED', 'email' => 'i@x.com'],
        ])
        ->assertOk();

    $this->assertDatabaseMissing('tenants', ['unit_id' => $resp->json('data.id')]);
})->with(['owner_occupied', 'vacant']);

it('forces organization_id and estate_id from auth + route — clients cannot spoof them', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $other  = createTenant();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.unit', $estate), [
            'unit_number'    => 'A1',
            'occupancy_type' => 'owner_occupied',
            'organization_id'      => $other->id,                                   // ← spoof attempt
            'estate_id'      => '00000000-0000-0000-0000-000000000000',       // ← spoof attempt
            'owner'          => ['full_name' => 'X', 'email' => 'x@x.com'],
        ])
        ->assertOk();

    expect($resp->json('data.organization_id'))->toBe($user->organization_id);
    expect($resp->json('data.estate_id'))->toBe($estate->id);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/.../units/{unit}  —  update                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('updates a unit with a partial payload — untouched fields remain unchanged', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate, ['unit_number' => 'A1', 'levy_override' => 100, 'rent_amount' => 5000]);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
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
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);
    attachOwner($unit, ['full_name' => 'Old Name', 'email' => 'old@x.com']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
            'owner' => ['full_name' => 'New Name', 'email' => 'new@x.com'],
        ])
        ->assertOk();

    $this->assertDatabaseHas('owners', ['unit_id' => $unit->id, 'full_name' => 'New Name', 'email' => 'new@x.com']);
});

it('updates the active tenant when tenant.* is provided', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate, ['occupancy_type' => 'tenant_occupied']);
    attachOwner($unit);
    Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Old Tenant',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
            'tenant'         => ['full_name' => 'New Tenant'],
        ])
        ->assertOk();

    $this->assertDatabaseHas('tenants', ['unit_id' => $unit->id, 'full_name' => 'New Tenant', 'is_active' => true]);
});

it('creates a new active tenant and flips occupancy to tenant_occupied when none exists', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate, ['occupancy_type' => 'owner_occupied']);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
            'tenant'         => [
                'full_name'   => 'Brand New',
                'email'       => 'new@tenant.com',
                'lease_start' => '2026-01-01',
                'lease_end'   => '2027-01-01',
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('units', ['id' => $unit->id, 'occupancy_type' => 'tenant_occupied']);
    $this->assertDatabaseHas('tenants', ['unit_id' => $unit->id, 'full_name' => 'Brand New', 'is_active' => true]);
});

it('writes a UnitActivity entry per category that actually changed (sharing one batch_id)', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate, ['unit_number' => 'A1', 'rent_amount' => 5000]);
    attachOwner($unit, ['full_name' => 'Owner A']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
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
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
            'occupancy_type' => 'tornado',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

it('rejects update with invalid status', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
            'status' => 'imaginary',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects update with invalid owner.email', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
            'owner' => ['email' => 'not-email'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner.email']);
});

it('rejects update where lease_end is before lease_start', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate, ['occupancy_type' => 'tenant_occupied']);
    attachOwner($unit);
    Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
            'tenant'         => ['lease_start' => '2026-06-01', 'lease_end' => '2026-05-01'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant.lease_end']);
});

it('rejects update switching to tenant_occupied on a sectional_title estate', function () {
    $user   = adminUser();
    $estate = makeEstate($user, ['type' => 'sectional_title']);
    $unit   = makeUnit($estate, ['occupancy_type' => 'owner_occupied']);
    attachOwner($unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.unit', ['estate' => $estate, 'unit' => $unit]), [
            'occupancy_type' => 'tenant_occupied',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupancy_type']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/.../units/{unit}  —  single                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('deletes a single unit and returns the success payload', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit', ['estate' => $estate, 'unit' => $unit]))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Unit deleted']);

    $this->assertDatabaseMissing('units', ['id' => $unit->id]);
});

it('returns 404 when deleting an unknown unit id', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit', ['estate' => $estate, 'unit' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/.../units  —  bulk                                            ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('bulk deletes own-estate units and pluralises the message', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $units  = Unit::factory()->count(3)->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), ['unit_ids' => $units->pluck('id')->all()])
        ->assertOk()
        ->assertJson(['message' => '3 Units deleted']);

    foreach ($units as $u) {
        $this->assertDatabaseMissing('units', ['id' => $u->id]);
    }
});

it('uses singular label for one unit', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), ['unit_ids' => [$unit->id]])
        ->assertOk()
        ->assertJson(['message' => '1 Unit deleted']);
});

it('only deletes units that belong to the route estate', function () {
    $user    = adminUser();
    $estateA = makeEstate($user);
    $estateB = makeEstate($user);
    $unitInA = makeUnit($estateA);
    $unitInB = makeUnit($estateB);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estateA), ['unit_ids' => [$unitInA->id, $unitInB->id]])
        ->assertOk()
        ->assertJson(['message' => '1 Unit deleted']);

    $this->assertDatabaseMissing('units', ['id' => $unitInA->id]);
    $this->assertDatabaseHas('units', ['id' => $unitInB->id]);
});

it('returns 500 when none of the supplied unit_ids belong to the route estate', function () {
    $user    = adminUser();
    $estateA = makeEstate($user);
    $estateB = makeEstate($user);
    $unitInB = makeUnit($estateB);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estateA), ['unit_ids' => [$unitInB->id]])
        ->assertStatus(500);

    $this->assertDatabaseHas('units', ['id' => $unitInB->id]);
});

it('rejects non-uuid values in unit_ids', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $unit   = makeUnit($estate);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), ['unit_ids' => [$unit->id, 'not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_ids.1']);
});

it('rejects unit_ids that is not an array', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), ['unit_ids' => 'a-single-id'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_ids']);
});

it('returns 403 when bulk delete unit_ids is missing or empty (policy guard)', function (array $payload) {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.units', $estate), $payload)
        ->assertForbidden();
})->with([
    'missing' => [[]],
    'empty'   => [['unit_ids' => []]],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/.../units/bulk-import  —  rows                                  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('imports valid rows, detects duplicates, and reports per-row errors that escape request-level validation', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id, 'unit_number' => 'A01']);

    // Every row has the request-required keys (unit_number, occupancy_type, owner_full_name,
    // owner_email). Per-row service errors only fire for things the request schema doesn't
    // already enforce — e.g. tenant_email format. The case-insensitive `a01` row is a
    // duplicate of the existing A01 unit.
    $rows = [
        ['unit_number' => 'B02', 'occupancy_type' => 'owner_occupied',  'owner_full_name' => 'Owner B', 'owner_email' => 'b@x.com'],
        ['unit_number' => 'a01', 'occupancy_type' => 'owner_occupied',  'owner_full_name' => 'Dup',     'owner_email' => 'd@x.com'],
        ['unit_number' => 'C03', 'occupancy_type' => 'tenant_occupied', 'owner_full_name' => 'Owner C', 'owner_email' => 'c@x.com',
         'tenant_full_name' => 'Tenant C', 'tenant_email' => 'not-an-email'],
        ['unit_number' => 'D04', 'occupancy_type' => 'tenant_occupied', 'owner_full_name' => 'Owner D', 'owner_email' => 'd@x.com',
         'tenant_full_name' => 'Tenant D', 'tenant_email' => 'td@x.com', 'tenant_lease_start' => '2026-01-01', 'tenant_lease_end' => '2027-01-01'],
    ];

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $estate), ['rows' => $rows])
        ->assertOk()
        ->json();

    expect($body)->toHaveKeys(['imported', 'duplicates', 'error_count', 'errors', 'total', 'message']);
    expect($body['total'])->toBe(4);
    expect($body['imported'])->toBe(2);    // B02 + D04
    expect($body['duplicates'])->toBe(1);  // a01 ↔ existing A01
    expect($body['error_count'])->toBe(1); // C03 has invalid tenant_email
    expect($body['errors'][0]['row'])->toBe(3);
    expect($body['errors'][0]['errors'])->toContain("Tenant email 'not-an-email' is invalid.");

    $this->assertDatabaseHas('units', ['estate_id' => $estate->id, 'unit_number' => 'B02']);
    $this->assertDatabaseHas('units', ['estate_id' => $estate->id, 'unit_number' => 'D04']);
    $this->assertDatabaseMissing('units', ['estate_id' => $estate->id, 'unit_number' => 'C03']);
    $this->assertDatabaseHas('owners', ['unit_id' => Unit::where('unit_number', 'B02')->first()->id, 'email' => 'b@x.com']);
    $this->assertDatabaseHas('tenants', ['unit_id' => Unit::where('unit_number', 'D04')->first()->id, 'email' => 'td@x.com', 'is_active' => true]);
});

it('rejects bulk import when rows is missing', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $estate), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rows']);
});

it('rejects bulk import when rows is an empty array', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $estate), ['rows' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rows']);
});

it('rejects bulk import row missing required fields', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $estate), [
            'rows' => [
                ['occupancy_type' => 'owner_occupied', 'owner_full_name' => 'X', 'owner_email' => 'x@x.com'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rows.0.unit_number']);
});

it('rejects bulk import row with invalid occupancy_type', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $estate), [
            'rows' => [
                ['unit_number' => 'A1', 'occupancy_type' => 'rented', 'owner_full_name' => 'X', 'owner_email' => 'x@x.com'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rows.0.occupancy_type']);
});

it('rejects bulk import row with invalid owner_email', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.units', $estate), [
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
    $estate = makeEstate($user);

    $resp = $this->actingAs($user, 'api')
        ->get(route('api.v1.bulk.import.template', $estate))
        ->assertOk();

    expect($resp->headers->get('Content-Type'))->toContain('text/csv');
    expect($resp->headers->get('Content-Disposition'))->toContain('units-import-template.csv');
    expect($resp->getContent())->toContain('unit_number');
    expect($resp->getContent())->toContain('occupancy_type');
    expect($resp->getContent())->toContain('owner_full_name,owner_id_number,owner_email');
});

it('parses an uploaded CSV file into columns + rows', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $csv = "unit_number,occupancy_type,owner_full_name,owner_email\n"
         . "A01,owner_occupied,John,john@x.com\n"
         . "A02,vacant,Jane,jane@x.com\n";

    $file = UploadedFile::fake()->createWithContent('input.csv', $csv);

    $body = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.parse', $estate), ['file' => $file])
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
    $estate = makeEstate($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.parse', $estate), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('rejects bulk-import-parse when file is the wrong mime type', function () {
    $user   = adminUser();
    $estate = makeEstate($user);

    $file = UploadedFile::fake()->create('badtype.txt', 10, 'text/plain');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.bulk.import.parse', $estate), ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Export                                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('exports units as CSV with the expected headings', function () {
    $user   = adminUser();
    $estate = makeEstate($user, ['name' => 'Crystal Mews']);
    $unit   = makeUnit($estate, ['unit_number' => 'A01', 'occupancy_type' => 'owner_occupied', 'balance' => -250]);
    attachOwner($unit, ['full_name' => 'Joe', 'email' => 'joe@x.com']);

    $resp = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.units', $estate) . '?_format=csv');

    $resp->assertOk();
    expect($resp->headers->get('Content-Type'))->toContain('text/csv');
    expect($resp->headers->get('Content-Disposition'))->toContain('units-crystal-mews-' . now()->format('Y-m-d') . '.csv');

    // streamDownload responds with a StreamedResponse — capture the streamed body.
    $csv = $resp->streamedContent();
    // PHP 8.4 fputcsv quotes any value containing a non-alnum character.
    expect($csv)->toContain('"Unit #",Occupancy,"Owner Name","Owner Email","Tenant Name","Tenant Email",Balance');
    expect($csv)->toContain('A01,"Owner Occupied",Joe,joe@x.com');
    expect($csv)->toContain('-250.00');
});

it('respects export filters (occupancy_type narrows the dataset)', function () {
    $user   = adminUser();
    $estate = makeEstate($user);
    $a = makeUnit($estate, ['unit_number' => 'A1', 'occupancy_type' => 'owner_occupied']);
    $b = makeUnit($estate, ['unit_number' => 'B1', 'occupancy_type' => 'tenant_occupied']);
    attachOwner($a);
    attachOwner($b);

    $csv = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.units', $estate) . '?_format=csv&occupancy_type=owner_occupied')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('A1');
    expect($csv)->not->toContain('B1');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Cross-estate / cross-tenant isolation                                    ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('cross-estate unit show returns 404', function () {
    $user   = adminUser();
    $right  = makeEstate($user);
    $wrong  = makeEstate($user);
    $unit   = makeUnit($right);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit', ['estate' => $wrong, 'unit' => $unit]))
        ->assertNotFound();
});

it('cross-tenant unit listing returns 404', function () {
    $user        = adminUser();
    $otherEstate = Estate::factory()->create(['organization_id' => createTenant()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.units', $otherEstate))
        ->assertNotFound();
});
