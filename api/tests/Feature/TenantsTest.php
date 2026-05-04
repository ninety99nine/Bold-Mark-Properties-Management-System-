<?php

use App\Models\Estate;
use App\Models\Unit;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function tenantSetup(string $estateType = 'residential_rental', string $occupancy = 'tenant_occupied'): array
{
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id, 'type' => $estateType]);
    $unit   = Unit::factory()->create([
        'estate_id'      => $estate->id,
        'organization_id'      => $user->organization_id,
        'occupancy_type' => $occupancy,
    ]);

    return [$user, $estate, $unit];
}

function makeUnitTenant(\App\Models\User $user, Unit $unit, array $overrides = []): Tenant
{
    return Tenant::factory()->create(array_merge([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => false,
    ], $overrides));
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unauthenticated access                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on every unit-tenant route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.unit.organizations',     ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.create.tenant',    ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.unit.organizations',   ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.show.tenant',      ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'tenant' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.tenant',    ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'tenant' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.move.out.tenant',  ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'tenant' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.reinstate.tenant', ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'tenant' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.tenant',    ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'tenant' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.upload.lease.document', ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'tenant' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.lease.document', ['estate' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'tenant' => '00000000-0000-0000-0000-000000000000']],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/.../organizations  —  index (full tenant history for a unit)           ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns the paginator structure with the resource keys the UnitDetailPage reads', function () {
    [$user, $estate, $unit] = tenantSetup();
    Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Active Jane',
    ]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'unit_id', 'organization_id', 'full_name', 'email', 'phone',
                        'is_active', 'lease_start', 'lease_end',
                        'lease_document_url', 'lease_document_name',
                        'move_out_date', 'move_out_reason', 'move_out_notes']],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($resp->json('meta.total'))->toBe(1);
    expect($resp->json('data.0.full_name'))->toBe('Active Jane');
    expect($resp->json('data.0.is_active'))->toBeTrue();
});

it('returns full tenant history including inactive organizations', function () {
    [$user, $estate, $unit] = tenantSetup();
    makeUnitTenant($user, $unit, ['is_active' => false]);
    makeUnitTenant($user, $unit, ['is_active' => false]);
    Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
});

it('orders organizations by latest created_at by default', function () {
    [$user, $estate, $unit] = tenantSetup();
    $oldest = makeUnitTenant($user, $unit, ['created_at' => now()->subDays(3)]);
    $middle = makeUnitTenant($user, $unit, ['created_at' => now()->subDay()]);
    $newest = makeUnitTenant($user, $unit, ['created_at' => now()]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$newest->id, $middle->id, $oldest->id]);
});

it('only returns organizations for the requested unit', function () {
    [$user, $estate, $unit] = tenantSetup();
    $other = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    makeUnitTenant($user, $unit);
    makeUnitTenant($user, $other);
    makeUnitTenant($user, $other);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters — is_active boolean coercion
// ──────────────────────────────────────────────────────────────────────────────

it('filters organizations by is_active=true', function (string $truthy) {
    [$user, $estate, $unit] = tenantSetup();
    Tenant::factory()->count(2)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);
    Tenant::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]) . "?is_active={$truthy}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
})->with(['true', '1']);

it('filters organizations by is_active=false', function (string $falsy) {
    [$user, $estate, $unit] = tenantSetup();
    Tenant::factory()->count(2)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);
    Tenant::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]) . "?is_active={$falsy}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
})->with(['false', '0']);

// ──────────────────────────────────────────────────────────────────────────────
// Sort (_sort)
// ──────────────────────────────────────────────────────────────────────────────

it('sorts organizations by full_name asc', function () {
    [$user, $estate, $unit] = tenantSetup();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $n) {
        makeUnitTenant($user, $unit, ['full_name' => $n]);
    }

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]) . '?_sort=full_name:asc')
        ->assertOk()
        ->json('data.*.full_name');

    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('sorts organizations by full_name desc', function () {
    [$user, $estate, $unit] = tenantSetup();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $n) {
        makeUnitTenant($user, $unit, ['full_name' => $n]);
    }

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]) . '?_sort=full_name:desc')
        ->assertOk()
        ->json('data.*.full_name');

    expect($names)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range
// ──────────────────────────────────────────────────────────────────────────────

it('filters organizations by _date_range=today', function () {
    [$user, $estate, $unit] = tenantSetup();
    makeUnitTenant($user, $unit, ['created_at' => now()]);
    makeUnitTenant($user, $unit, ['created_at' => now()->subDays(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]) . '?_date_range=today')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters organizations by _date_range=custom with start + end', function () {
    [$user, $estate, $unit] = tenantSetup();
    makeUnitTenant($user, $unit, ['created_at' => Carbon::parse('2026-02-15')]);
    makeUnitTenant($user, $unit, ['created_at' => Carbon::parse('2026-04-15')]);
    makeUnitTenant($user, $unit, ['created_at' => Carbon::parse('2026-06-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]) . '?_date_range=custom&_date_range_start=2026-03-01&_date_range_end=2026-05-31')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (Postgres-only)
// ──────────────────────────────────────────────────────────────────────────────

it('searches organizations by full_name / email / phone', function () {
    [$user, $estate, $unit] = tenantSetup();
    makeUnitTenant($user, $unit, ['full_name' => 'Crystal']);
    makeUnitTenant($user, $unit, ['full_name' => 'River']);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$estate, $unit]) . '?_search=Crystal')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
})->skip('Tenant::scopeSearch uses ilike (Postgres-only). Make portable to enable in SQLite tests.');

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/.../organizations/{tenant}  —  show                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns a single tenant with unit + estate eager-loaded', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Jane Doe',
    ]);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.tenant', [$estate, $unit, $tenant]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'full_name', 'email', 'is_active',
                       'unit' => ['id', 'unit_number',
                                  'estate' => ['id', 'name']]],
        ])
        ->json();

    expect($body['data']['id'])->toBe($tenant->id);
    expect($body['data']['full_name'])->toBe('Jane Doe');
    expect($body['data']['unit']['estate']['id'])->toBe($estate->id);
});

it('returns 404 for an unknown unit-tenant id', function () {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.tenant', [$estate, $unit, '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/.../organizations  —  create (move-in)                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// Validation rules

it('rejects move-in without full_name', function () {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), ['email' => 'x@x.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('rejects move-in without email', function () {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), ['full_name' => 'X'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects move-in with invalid email format', function (string $bad) {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), [
            'full_name' => 'X',
            'email'     => $bad,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
})->with(['notanemail', '@x.com', 'a b@x.com']);

it('rejects move-in with full_name > 255 chars', function () {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), [
            'full_name' => str_repeat('z', 256),
            'email'     => 'x@x.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('rejects move-in with phone > 30 chars', function () {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), [
            'full_name' => 'X',
            'email'     => 'x@x.com',
            'phone'     => str_repeat('1', 31),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('rejects move-in with negative rent_amount', function () {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), [
            'full_name'   => 'X',
            'email'       => 'x@x.com',
            'rent_amount' => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rent_amount']);
});

it('rejects move-in with lease_end on or before lease_start', function (string $start, string $end) {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), [
            'full_name'   => 'X',
            'email'       => 'x@x.com',
            'lease_start' => $start,
            'lease_end'   => $end,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_end']);
})->with([
    'end before start'  => ['2026-06-01', '2026-05-01'],
    'end same as start' => ['2026-06-01', '2026-06-01'],
]);

// Successful move-in & side effects

it('creates a tenant, archives any existing active tenant, and flips occupancy to tenant_occupied', function () {
    [$user, $estate, $unit] = tenantSetup('residential_rental', 'owner_occupied');
    $previousActive = Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Previous',
    ]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), [
            'full_name'   => 'New Tenant',
            'email'       => 'new@x.com',
            'phone'       => '+27 11 555',
            'rent_amount' => 5000,
            'lease_start' => '2026-01-01',
            'lease_end'   => '2027-01-01',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Created successfully')
        ->assertJsonPath('data.full_name', 'New Tenant')
        ->assertJsonPath('data.is_active', true);

    // Previously-active tenant must be archived.
    expect($previousActive->fresh()->is_active)->toBeFalse();

    // Unit occupancy flipped to tenant_occupied and rent_amount synced.
    $unit->refresh();
    expect($unit->occupancy_type->value)->toBe('tenant_occupied');
    expect((float) $unit->rent_amount)->toBe(5000.0);

    // organization_id forced from authenticated user, not from payload.
    expect($resp->json('data.organization_id'))->toBe($user->organization_id);

    // Activity log entry created.
    $this->assertDatabaseHas('unit_activities', [
        'unit_id'  => $unit->id,
        'event'    => 'Moved in tenant',
        'category' => 'tenant',
    ]);
});

it('does not let the client spoof organization_id, unit_id, or is_active on move-in', function () {
    [$user, $estate, $unit] = tenantSetup();
    $other = createTenant();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.tenant', [$estate, $unit]), [
            'full_name' => 'X',
            'email'     => 'x@x.com',
            'organization_id' => $other->id,                                   // ← spoof
            'unit_id'   => '00000000-0000-0000-0000-000000000000',       // ← spoof
            'is_active' => false,                                         // ← service overrides to true
        ])
        ->assertOk();

    expect($resp->json('data.organization_id'))->toBe($user->organization_id);
    expect($resp->json('data.unit_id'))->toBe($unit->id);
    expect($resp->json('data.is_active'))->toBeTrue();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/.../organizations/{tenant}  —  update                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('updates a tenant with a partial payload', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Old',
        'email'     => 'old@x.com',
        'phone'     => '+27 11 0000',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant', [$estate, $unit, $tenant]), [
            'full_name' => 'New',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.full_name', 'New')
        ->assertJsonPath('data.email', 'old@x.com')
        ->assertJsonPath('data.phone', '+27 11 0000');
});

it('keeps unit.rent_amount in sync when tenant rent_amount is updated', function () {
    [$user, $estate, $unit] = tenantSetup();
    $unit->update(['rent_amount' => 1000]);
    $tenant = Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant', [$estate, $unit, $tenant]), [
            'rent_amount' => 7500,
        ])
        ->assertOk();

    expect((float) $unit->fresh()->rent_amount)->toBe(7500.0);
});

it('rejects update with invalid email', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant', [$estate, $unit, $tenant]), [
            'email' => 'not-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects update with lease_end on or before lease_start', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant', [$estate, $unit, $tenant]), [
            'lease_start' => '2026-06-01',
            'lease_end'   => '2026-05-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_end']);
});

it('accepts move_out_* fields on update (used by EditMoveOut modal)', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => false,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant', [$estate, $unit, $tenant]), [
            'move_out_date'   => '2026-04-01',
            'move_out_reason' => 'lease_expired',
            'move_out_notes'  => 'returned keys',
        ])
        ->assertOk();

    $fresh = $tenant->fresh();
    expect($fresh->move_out_date->toDateString())->toBe('2026-04-01');
    expect($fresh->move_out_reason)->toBe('lease_expired');
    expect($fresh->move_out_notes)->toBe('returned keys');
});

it('rejects update with move_out_reason > 255 chars', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant', [$estate, $unit, $tenant]), [
            'move_out_reason' => str_repeat('z', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['move_out_reason']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/.../organizations/{tenant}/move-out                               ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('moves a tenant out: deactivates them, sets unit to vacant, logs an activity entry', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Jane',
    ]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.tenant', [$estate, $unit, $tenant]), [
            'move_out_date'   => '2026-05-01',
            'move_out_reason' => 'lease_expired',
            'move_out_notes'  => 'amicable',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Tenant moved out successfully');

    $fresh = $tenant->fresh();
    expect($fresh->is_active)->toBeFalse();
    expect($fresh->move_out_date->toDateString())->toBe('2026-05-01');
    expect($fresh->move_out_reason)->toBe('lease_expired');
    expect($fresh->move_out_notes)->toBe('amicable');

    expect($unit->fresh()->occupancy_type->value)->toBe('vacant');

    $this->assertDatabaseHas('unit_activities', [
        'unit_id'  => $unit->id,
        'event'    => 'Moved out tenant',
        'category' => 'tenant',
    ]);

    expect($resp->json('data.id'))->toBe($tenant->id);
});

it('move-out works with no payload — defaults to just deactivating', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.tenant', [$estate, $unit, $tenant]), [])
        ->assertOk();

    expect($tenant->fresh()->is_active)->toBeFalse();
});

it('rejects move-out with invalid date', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.tenant', [$estate, $unit, $tenant]), [
            'move_out_date' => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['move_out_date']);
});

it('rejects move-out reason > 255 chars', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.tenant', [$estate, $unit, $tenant]), [
            'move_out_reason' => str_repeat('a', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['move_out_reason']);
});

it('rejects move-out notes > 2000 chars', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.tenant', [$estate, $unit, $tenant]), [
            'move_out_notes' => str_repeat('a', 2001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['move_out_notes']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/.../organizations/{tenant}/reinstate                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('reinstates an inactive tenant: sets is_active true, unit back to tenant_occupied, logs activity', function () {
    [$user, $estate, $unit] = tenantSetup('residential_rental', 'vacant');
    $tenant = Tenant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => false,
        'full_name' => 'Returning Jane',
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.reinstate.tenant', [$estate, $unit, $tenant]), [])
        ->assertOk()
        ->assertJsonPath('message', 'Tenant reinstated successfully')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.full_name', 'Returning Jane');

    expect($unit->fresh()->occupancy_type->value)->toBe('tenant_occupied');

    $this->assertDatabaseHas('unit_activities', [
        'unit_id'  => $unit->id,
        'event'    => 'Reinstated tenant',
        'category' => 'tenant',
    ]);
});

it('refuses to reinstate when another tenant is already active on the unit', function () {
    [$user, $estate, $unit] = tenantSetup();
    Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);
    $past = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => false]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.reinstate.tenant', [$estate, $unit, $past]), [])
        ->assertStatus(500);

    expect($past->fresh()->is_active)->toBeFalse();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE single + bulk                                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('deletes a single tenant', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = makeUnitTenant($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.tenant', [$estate, $unit, $tenant]))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Tenant deleted']);

    $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
});

it('returns 404 when deleting an unknown tenant id', function () {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.tenant', [$estate, $unit, '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

it('bulk deletes organizations for the unit and pluralises the message', function () {
    [$user, $estate, $unit] = tenantSetup();
    $organizations = Tenant::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => false]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$estate, $unit]), [
            'tenant_ids' => $organizations->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJson(['message' => '3 Tenants deleted']);

    foreach ($organizations as $t) {
        $this->assertDatabaseMissing('tenants', ['id' => $t->id]);
    }
});

it('uses the singular label when bulk-deleting one', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = makeUnitTenant($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$estate, $unit]), [
            'tenant_ids' => [$tenant->id],
        ])
        ->assertOk()
        ->assertJson(['message' => '1 Tenant deleted']);
});

it('only deletes organizations that belong to the route unit', function () {
    [$user, $estate, $unit] = tenantSetup();
    $other = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $own   = makeUnitTenant($user, $unit);
    $cross = makeUnitTenant($user, $other);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$estate, $unit]), [
            'tenant_ids' => [$own->id, $cross->id],
        ])
        ->assertOk()
        ->assertJson(['message' => '1 Tenant deleted']);

    $this->assertDatabaseMissing('tenants', ['id' => $own->id]);
    $this->assertDatabaseHas('tenants',     ['id' => $cross->id]);
});

it('returns 500 when no supplied id belongs to the route unit', function () {
    [$user, $estate, $unit] = tenantSetup();
    $other = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $cross = makeUnitTenant($user, $other);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$estate, $unit]), [
            'tenant_ids' => [$cross->id],
        ])
        ->assertStatus(500);

    $this->assertDatabaseHas('tenants', ['id' => $cross->id]);
});

it('rejects bulk delete with non-uuid id', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = makeUnitTenant($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$estate, $unit]), [
            'tenant_ids' => [$tenant->id, 'not-a-uuid'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_ids.1']);
});

it('returns 403 when bulk delete tenant_ids is missing or empty (policy guard)', function (array $payload) {
    [$user, $estate, $unit] = tenantSetup();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$estate, $unit]), $payload)
        ->assertForbidden();
})->with([
    'missing' => [[]],
    'empty'   => [['tenant_ids' => []]],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Lease document — upload / delete                                         ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('uploads a lease document (PDF) and persists URL + filename', function () {
    Storage::fake('public');

    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $file = UploadedFile::fake()->create('lease.pdf', 100, 'application/pdf');

    $resp = $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.lease.document', [$estate, $unit, $tenant]), [
            'lease_document' => $file,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.lease_document_name', 'lease.pdf');

    expect($resp->json('data.lease_document_url'))->toBeString()->not->toBeEmpty();
    expect($tenant->fresh()->lease_document_name)->toBe('lease.pdf');
});

it('rejects lease upload with no file', function () {
    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.lease.document', [$estate, $unit, $tenant]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_document']);
});

it('rejects lease upload with a wrong mime type', function () {
    Storage::fake('public');

    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $bad = UploadedFile::fake()->create('lease.txt', 10, 'text/plain');

    $this->actingAs($user, 'api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post(route('api.v1.upload.lease.document', [$estate, $unit, $tenant]), [
            'lease_document' => $bad,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_document']);
});

it('rejects lease upload larger than 5 MB', function () {
    Storage::fake('public');

    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $tooBig = UploadedFile::fake()->create('huge.pdf', 5121, 'application/pdf');

    $this->actingAs($user, 'api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post(route('api.v1.upload.lease.document', [$estate, $unit, $tenant]), [
            'lease_document' => $tooBig,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_document']);
});

it('replaces an existing lease document on a second upload', function () {
    Storage::fake('public');

    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $first = UploadedFile::fake()->create('first.pdf', 50, 'application/pdf');
    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.lease.document', [$estate, $unit, $tenant]), ['lease_document' => $first])
        ->assertOk();

    $second = UploadedFile::fake()->create('second.pdf', 50, 'application/pdf');
    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.lease.document', [$estate, $unit, $tenant]), ['lease_document' => $second])
        ->assertOk()
        ->assertJsonPath('data.lease_document_name', 'second.pdf');
});

it('clears the lease document via DELETE', function () {
    Storage::fake('public');

    [$user, $estate, $unit] = tenantSetup();
    $tenant = Tenant::factory()->create([
        'unit_id'             => $unit->id,
        'organization_id'           => $user->organization_id,
        'is_active'           => true,
        'lease_document_url'  => '/storage/lease.pdf',
        'lease_document_name' => 'lease.pdf',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.lease.document', [$estate, $unit, $tenant]))
        ->assertOk()
        ->assertJsonPath('data.lease_document_url', null)
        ->assertJsonPath('data.lease_document_name', null);

    $fresh = $tenant->fresh();
    expect($fresh->lease_document_url)->toBeNull();
    expect($fresh->lease_document_name)->toBeNull();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Cross-tenant isolation (security gap, characterised)                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('CHARACTERIZATION: cross-tenant tenant show currently returns 200 (should be 404)', function () {
    $user        = adminUser();
    $other       = createTenant();
    $otherEstate = Estate::factory()->create(['organization_id' => $other->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'organization_id' => $other->id]);
    $otherTenant = Tenant::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.tenant', [$otherEstate, $otherUnit, $otherTenant]))
        ->assertOk();
});

it('SECURITY: cross-tenant tenant show should return 404', function () {
    $user        = adminUser();
    $other       = createTenant();
    $otherEstate = Estate::factory()->create(['organization_id' => $other->id]);
    $otherUnit   = Unit::factory()->create(['estate_id' => $otherEstate->id, 'organization_id' => $other->id]);
    $otherTenant = Tenant::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.tenant', [$otherEstate, $otherUnit, $otherTenant]))
        ->assertNotFound();
})->skip('SECURITY GAP — TenantPolicy::view returns true; route bindings are not tenant-scoped.');
