<?php

use App\Models\Community;
use App\Models\Unit;
use App\Models\Occupant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function occupantSetup(string $communityType = 'residential_rental', string $occupancy = 'occupant_occupied'): array
{
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id, 'entity_type' => $communityType]);
    $unit   = Unit::factory()->create([
        'community_id'      => $community->id,
        'organization_id'      => $user->organization_id,
        'occupancy_type' => $occupancy,
    ]);

    return [$user, $community, $unit];
}

function makeUnitOccupant(\App\Models\User $user, Unit $unit, array $overrides = []): Occupant
{
    return Occupant::factory()->create(array_merge([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => false,
    ], $overrides));
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unauthenticated access                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on every unit-occupant route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.unit.organizations',     ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.create.occupant',    ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.unit.organizations',   ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.show.occupant',      ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'occupant' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.occupant',    ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'occupant' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.move.out.occupant',  ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'occupant' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.reinstate.occupant', ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'occupant' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.occupant',    ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'occupant' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.upload.lease.document', ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'occupant' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.lease.document', ['community' => '00000000-0000-0000-0000-000000000000', 'unit' => '00000000-0000-0000-0000-000000000000', 'occupant' => '00000000-0000-0000-0000-000000000000']],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/.../organizations  —  index (full occupant history for a unit)           ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns the paginator structure with the resource keys the UnitDetailPage reads', function () {
    [$user, $community, $unit] = occupantSetup();
    Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Active Jane',
    ]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]))
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

it('returns full occupant history including inactive organizations', function () {
    [$user, $community, $unit] = occupantSetup();
    makeUnitOccupant($user, $unit, ['is_active' => false]);
    makeUnitOccupant($user, $unit, ['is_active' => false]);
    Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
});

it('orders organizations by latest created_at by default', function () {
    [$user, $community, $unit] = occupantSetup();
    $oldest = makeUnitOccupant($user, $unit, ['created_at' => now()->subDays(3)]);
    $middle = makeUnitOccupant($user, $unit, ['created_at' => now()->subDay()]);
    $newest = makeUnitOccupant($user, $unit, ['created_at' => now()]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$newest->id, $middle->id, $oldest->id]);
});

it('only returns organizations for the requested unit', function () {
    [$user, $community, $unit] = occupantSetup();
    $other = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    makeUnitOccupant($user, $unit);
    makeUnitOccupant($user, $other);
    makeUnitOccupant($user, $other);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters — is_active boolean coercion
// ──────────────────────────────────────────────────────────────────────────────

it('filters organizations by is_active=true', function (string $truthy) {
    [$user, $community, $unit] = occupantSetup();
    Occupant::factory()->count(2)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);
    Occupant::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]) . "?is_active={$truthy}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
})->with(['true', '1']);

it('filters organizations by is_active=false', function (string $falsy) {
    [$user, $community, $unit] = occupantSetup();
    Occupant::factory()->count(2)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);
    Occupant::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]) . "?is_active={$falsy}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
})->with(['false', '0']);

// ──────────────────────────────────────────────────────────────────────────────
// Sort (_sort)
// ──────────────────────────────────────────────────────────────────────────────

it('sorts organizations by full_name asc', function () {
    [$user, $community, $unit] = occupantSetup();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $n) {
        makeUnitOccupant($user, $unit, ['full_name' => $n]);
    }

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]) . '?_sort=full_name:asc')
        ->assertOk()
        ->json('data.*.full_name');

    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('sorts organizations by full_name desc', function () {
    [$user, $community, $unit] = occupantSetup();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $n) {
        makeUnitOccupant($user, $unit, ['full_name' => $n]);
    }

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]) . '?_sort=full_name:desc')
        ->assertOk()
        ->json('data.*.full_name');

    expect($names)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range
// ──────────────────────────────────────────────────────────────────────────────

it('filters organizations by _date_range=today', function () {
    [$user, $community, $unit] = occupantSetup();
    makeUnitOccupant($user, $unit, ['created_at' => now()]);
    makeUnitOccupant($user, $unit, ['created_at' => now()->subDays(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]) . '?_date_range=today')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters organizations by _date_range=custom with start + end', function () {
    [$user, $community, $unit] = occupantSetup();
    makeUnitOccupant($user, $unit, ['created_at' => Carbon::parse('2026-02-15')]);
    makeUnitOccupant($user, $unit, ['created_at' => Carbon::parse('2026-04-15')]);
    makeUnitOccupant($user, $unit, ['created_at' => Carbon::parse('2026-06-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]) . '?_date_range=custom&_date_range_start=2026-03-01&_date_range_end=2026-05-31')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (Postgres-only)
// ──────────────────────────────────────────────────────────────────────────────

it('searches organizations by full_name / email / phone', function () {
    [$user, $community, $unit] = occupantSetup();
    makeUnitOccupant($user, $unit, ['full_name' => 'Crystal']);
    makeUnitOccupant($user, $unit, ['full_name' => 'River']);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.unit.organizations', [$community, $unit]) . '?_search=Crystal')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/.../organizations/{occupant}  —  show                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns a single occupant with unit + community eager-loaded', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Jane Doe',
    ]);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.occupant', [$community, $unit, $occupant]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'full_name', 'email', 'is_active',
                       'unit' => ['id', 'unit_number',
                                  'community' => ['id', 'name']]],
        ])
        ->json();

    expect($body['data']['id'])->toBe($occupant->id);
    expect($body['data']['full_name'])->toBe('Jane Doe');
    expect($body['data']['unit']['community']['id'])->toBe($community->id);
});

it('returns 404 for an unknown unit-occupant id', function () {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.occupant', [$community, $unit, '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/.../organizations  —  create (move-in)                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// Validation rules

it('rejects move-in without full_name', function () {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), ['email' => 'x@x.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('rejects move-in without email', function () {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), ['full_name' => 'X'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects move-in with invalid email format', function (string $bad) {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), [
            'full_name' => 'X',
            'email'     => $bad,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
})->with(['notanemail', '@x.com', 'a b@x.com']);

it('rejects move-in with full_name > 255 chars', function () {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), [
            'full_name' => str_repeat('z', 256),
            'email'     => 'x@x.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('rejects move-in with phone > 30 chars', function () {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), [
            'full_name' => 'X',
            'email'     => 'x@x.com',
            'phone'     => str_repeat('1', 31),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('rejects move-in with negative rent_amount', function () {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), [
            'full_name'   => 'X',
            'email'       => 'x@x.com',
            'rent_amount' => -1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rent_amount']);
});

it('rejects move-in with lease_end on or before lease_start', function (string $start, string $end) {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), [
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

it('creates a occupant, archives any existing active occupant, and flips occupancy to occupant_occupied', function () {
    [$user, $community, $unit] = occupantSetup('residential_rental', 'owner_occupied');
    $previousActive = Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Previous',
    ]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), [
            'full_name'   => 'New Occupant',
            'email'       => 'new@x.com',
            'phone'       => '+27 11 555',
            'rent_amount' => 5000,
            'lease_start' => '2026-01-01',
            'lease_end'   => '2027-01-01',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Created successfully')
        ->assertJsonPath('data.full_name', 'New Occupant')
        ->assertJsonPath('data.is_active', true);

    // Previously-active occupant must be archived.
    expect($previousActive->fresh()->is_active)->toBeFalse();

    // Unit occupancy flipped to occupant_occupied and rent_amount synced.
    $unit->refresh();
    expect($unit->occupancy_type->value)->toBe('occupant_occupied');
    expect((float) $unit->rent_amount)->toBe(5000.0);

    // organization_id forced from authenticated user, not from payload.
    expect($resp->json('data.organization_id'))->toBe($user->organization_id);

    // Activity log entry created.
    $this->assertDatabaseHas('unit_activities', [
        'unit_id'  => $unit->id,
        'event'    => 'Moved in occupant',
        'category' => 'occupant',
    ]);
});

it('does not let the client spoof organization_id, unit_id, or is_active on move-in', function () {
    [$user, $community, $unit] = occupantSetup();
    $other = createOrganization();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.occupant', [$community, $unit]), [
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
// ║ PUT /v1/.../organizations/{occupant}  —  update                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('updates a occupant with a partial payload', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Old',
        'email'     => 'old@x.com',
        'phone'     => '+27 11 0000',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.occupant', [$community, $unit, $occupant]), [
            'full_name' => 'New',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.full_name', 'New')
        ->assertJsonPath('data.email', 'old@x.com')
        ->assertJsonPath('data.phone', '+27 11 0000');
});

it('keeps unit.rent_amount in sync when occupant rent_amount is updated', function () {
    [$user, $community, $unit] = occupantSetup();
    $unit->update(['rent_amount' => 1000]);
    $occupant = Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.occupant', [$community, $unit, $occupant]), [
            'rent_amount' => 7500,
        ])
        ->assertOk();

    expect((float) $unit->fresh()->rent_amount)->toBe(7500.0);
});

it('rejects update with invalid email', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.occupant', [$community, $unit, $occupant]), [
            'email' => 'not-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects update with lease_end on or before lease_start', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.occupant', [$community, $unit, $occupant]), [
            'lease_start' => '2026-06-01',
            'lease_end'   => '2026-05-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_end']);
});

it('accepts move_out_* fields on update (used by EditMoveOut modal)', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => false,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.occupant', [$community, $unit, $occupant]), [
            'move_out_date'   => '2026-04-01',
            'move_out_reason' => 'lease_expired',
            'move_out_notes'  => 'returned keys',
        ])
        ->assertOk();

    $fresh = $occupant->fresh();
    expect($fresh->move_out_date->toDateString())->toBe('2026-04-01');
    expect($fresh->move_out_reason)->toBe('lease_expired');
    expect($fresh->move_out_notes)->toBe('returned keys');
});

it('rejects update with move_out_reason > 255 chars', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.occupant', [$community, $unit, $occupant]), [
            'move_out_reason' => str_repeat('z', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['move_out_reason']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/.../organizations/{occupant}/move-out                               ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('moves a occupant out: deactivates them, sets unit to vacant, logs an activity entry', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
        'full_name' => 'Jane',
    ]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.occupant', [$community, $unit, $occupant]), [
            'move_out_date'   => '2026-05-01',
            'move_out_reason' => 'lease_expired',
            'move_out_notes'  => 'amicable',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Occupant moved out successfully');

    $fresh = $occupant->fresh();
    expect($fresh->is_active)->toBeFalse();
    expect($fresh->move_out_date->toDateString())->toBe('2026-05-01');
    expect($fresh->move_out_reason)->toBe('lease_expired');
    expect($fresh->move_out_notes)->toBe('amicable');

    expect($unit->fresh()->occupancy_type->value)->toBe('vacant');

    $this->assertDatabaseHas('unit_activities', [
        'unit_id'  => $unit->id,
        'event'    => 'Moved out occupant',
        'category' => 'occupant',
    ]);

    expect($resp->json('data.id'))->toBe($occupant->id);
});

it('move-out works with no payload — defaults to just deactivating', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => true,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.occupant', [$community, $unit, $occupant]), [])
        ->assertOk();

    expect($occupant->fresh()->is_active)->toBeFalse();
});

it('rejects move-out with invalid date', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.occupant', [$community, $unit, $occupant]), [
            'move_out_date' => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['move_out_date']);
});

it('rejects move-out reason > 255 chars', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.occupant', [$community, $unit, $occupant]), [
            'move_out_reason' => str_repeat('a', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['move_out_reason']);
});

it('rejects move-out notes > 2000 chars', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.move.out.occupant', [$community, $unit, $occupant]), [
            'move_out_notes' => str_repeat('a', 2001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['move_out_notes']);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/.../organizations/{occupant}/reinstate                              ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('reinstates an inactive occupant: sets is_active true, unit back to occupant_occupied, logs activity', function () {
    [$user, $community, $unit] = occupantSetup('residential_rental', 'vacant');
    $occupant = Occupant::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'is_active' => false,
        'full_name' => 'Returning Jane',
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.reinstate.occupant', [$community, $unit, $occupant]), [])
        ->assertOk()
        ->assertJsonPath('message', 'Occupant reinstated successfully')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.full_name', 'Returning Jane');

    expect($unit->fresh()->occupancy_type->value)->toBe('occupant_occupied');

    $this->assertDatabaseHas('unit_activities', [
        'unit_id'  => $unit->id,
        'event'    => 'Reinstated occupant',
        'category' => 'occupant',
    ]);
});

it('refuses to reinstate when another occupant is already active on the unit', function () {
    [$user, $community, $unit] = occupantSetup();
    Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);
    $past = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => false]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.reinstate.occupant', [$community, $unit, $past]), [])
        ->assertStatus(500);

    expect($past->fresh()->is_active)->toBeFalse();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE single + bulk                                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('deletes a single occupant', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = makeUnitOccupant($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.occupant', [$community, $unit, $occupant]))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Occupant deleted']);

    $this->assertDatabaseMissing('occupants', ['id' => $occupant->id]);
});

it('returns 404 when deleting an unknown occupant id', function () {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.occupant', [$community, $unit, '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

it('bulk deletes organizations for the unit and pluralises the message', function () {
    [$user, $community, $unit] = occupantSetup();
    $organizations = Occupant::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => false]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$community, $unit]), [
            'occupant_ids' => $organizations->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJson(['message' => '3 Occupants deleted']);

    foreach ($organizations as $t) {
        $this->assertDatabaseMissing('occupants', ['id' => $t->id]);
    }
});

it('uses the singular label when bulk-deleting one', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = makeUnitOccupant($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$community, $unit]), [
            'occupant_ids' => [$occupant->id],
        ])
        ->assertOk()
        ->assertJson(['message' => '1 Occupant deleted']);
});

it('only deletes organizations that belong to the route unit', function () {
    [$user, $community, $unit] = occupantSetup();
    $other = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $own   = makeUnitOccupant($user, $unit);
    $cross = makeUnitOccupant($user, $other);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$community, $unit]), [
            'occupant_ids' => [$own->id, $cross->id],
        ])
        ->assertOk()
        ->assertJson(['message' => '1 Occupant deleted']);

    $this->assertDatabaseMissing('occupants', ['id' => $own->id]);
    $this->assertDatabaseHas('occupants',     ['id' => $cross->id]);
});

it('returns 500 when no supplied id belongs to the route unit', function () {
    [$user, $community, $unit] = occupantSetup();
    $other = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $cross = makeUnitOccupant($user, $other);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$community, $unit]), [
            'occupant_ids' => [$cross->id],
        ])
        ->assertStatus(500);

    $this->assertDatabaseHas('occupants', ['id' => $cross->id]);
});

it('rejects bulk delete with non-uuid id', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = makeUnitOccupant($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$community, $unit]), [
            'occupant_ids' => [$occupant->id, 'not-a-uuid'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupant_ids.1']);
});

it('returns 403 when bulk delete occupant_ids is missing or empty (policy guard)', function (array $payload) {
    [$user, $community, $unit] = occupantSetup();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.unit.organizations', [$community, $unit]), $payload)
        ->assertForbidden();
})->with([
    'missing' => [[]],
    'empty'   => [['occupant_ids' => []]],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Lease document — upload / delete                                         ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('uploads a lease document (PDF) and persists URL + filename', function () {
    Storage::fake('public');

    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $file = UploadedFile::fake()->create('lease.pdf', 100, 'application/pdf');

    $resp = $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.lease.document', [$community, $unit, $occupant]), [
            'lease_document' => $file,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.lease_document_name', 'lease.pdf');

    expect($resp->json('data.lease_document_url'))->toBeString()->not->toBeEmpty();
    expect($occupant->fresh()->lease_document_name)->toBe('lease.pdf');
});

it('rejects lease upload with no file', function () {
    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.lease.document', [$community, $unit, $occupant]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_document']);
});

it('rejects lease upload with a wrong mime type', function () {
    Storage::fake('public');

    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $bad = UploadedFile::fake()->create('lease.txt', 10, 'text/plain');

    $this->actingAs($user, 'api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post(route('api.v1.upload.lease.document', [$community, $unit, $occupant]), [
            'lease_document' => $bad,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_document']);
});

it('rejects lease upload larger than 5 MB', function () {
    Storage::fake('public');

    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $tooBig = UploadedFile::fake()->create('huge.pdf', 5121, 'application/pdf');

    $this->actingAs($user, 'api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post(route('api.v1.upload.lease.document', [$community, $unit, $occupant]), [
            'lease_document' => $tooBig,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lease_document']);
});

it('replaces an existing lease document on a second upload', function () {
    Storage::fake('public');

    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'is_active' => true]);

    $first = UploadedFile::fake()->create('first.pdf', 50, 'application/pdf');
    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.lease.document', [$community, $unit, $occupant]), ['lease_document' => $first])
        ->assertOk();

    $second = UploadedFile::fake()->create('second.pdf', 50, 'application/pdf');
    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.lease.document', [$community, $unit, $occupant]), ['lease_document' => $second])
        ->assertOk()
        ->assertJsonPath('data.lease_document_name', 'second.pdf');
});

it('clears the lease document via DELETE', function () {
    Storage::fake('public');

    [$user, $community, $unit] = occupantSetup();
    $occupant = Occupant::factory()->create([
        'unit_id'             => $unit->id,
        'organization_id'           => $user->organization_id,
        'is_active'           => true,
        'lease_document_url'  => '/storage/lease.pdf',
        'lease_document_name' => 'lease.pdf',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.lease.document', [$community, $unit, $occupant]))
        ->assertOk()
        ->assertJsonPath('data.lease_document_url', null)
        ->assertJsonPath('data.lease_document_name', null);

    $fresh = $occupant->fresh();
    expect($fresh->lease_document_url)->toBeNull();
    expect($fresh->lease_document_name)->toBeNull();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Cross-occupant isolation                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('cross-occupant occupant show returns 404', function () {
    $user        = adminUser();
    $other       = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $other->id]);
    $otherUnit   = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $other->id]);
    $otherOccupant = Occupant::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.occupant', [$otherCommunity, $otherUnit, $otherOccupant]))
        ->assertNotFound();
});
