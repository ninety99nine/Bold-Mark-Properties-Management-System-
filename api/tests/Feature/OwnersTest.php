<?php

use App\Models\Ledger;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Support\Carbon;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function setupOwnerOccupant(): array
{
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id, 'entity_type' => 'residential_rental']);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    return [$user, $community, $unit];
}

function makeOwner(\App\Models\User $user, ?Unit $unit = null, array $overrides = []): Owner
{
    $unit = $unit ?? Unit::factory()->create([
        'community_id' => Community::factory()->create(['organization_id' => $user->organization_id])->id,
        'organization_id' => $user->organization_id,
    ]);

    return Owner::factory()->create(array_merge([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
    ], $overrides));
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unauthenticated access                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on every owner route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.owners'],
    ['delete', 'api.v1.delete.owners'],
    ['get',    'api.v1.show.owner',   ['owner' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.owner', ['owner' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.owner', ['owner' => '00000000-0000-0000-0000-000000000000']],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/owners  —  index                                                 ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns the paginator structure', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners'))
        ->assertOk()
        ->assertJsonStructure([
            'data'  => [['id', 'unit_id', 'organization_id', 'full_name', 'email', 'phone', 'id_number', 'address', 'created_at', 'updated_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta'  => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ]);

    expect($resp->json('meta.total'))->toBe(3);
    expect($resp->json('meta.per_page'))->toBe(15);
});

it('only returns owners belonging to the authenticated occupant', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    // Foreign-occupant owners must NOT appear.
    $other        = createOrganization();
    $otherCommunity  = Community::factory()->create(['organization_id' => $other->id]);
    $otherUnit    = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $other->id]);
    Owner::factory()->count(2)->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners'))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(3);
    foreach ($resp->json('data') as $row) {
        expect($row['organization_id'])->toBe($user->organization_id);
    }
});

it('returns an empty list when the occupant has no owners', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners'))
        ->assertOk()
        ->assertJson(['data' => [], 'meta' => ['total' => 0]]);
});

it('orders owners by latest created_at by default', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $oldest = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()->subDays(3)]);
    $middle = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()->subDay()]);
    $newest = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$newest->id, $middle->id, $oldest->id]);
});

it('eager-loads unit and unit.community so the OwnerDetailPage has the breadcrumb', function () {
    [$user, $community, $unit] = setupOwnerOccupant();
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners'))
        ->assertOk()
        ->json('data.0');

    expect($row['unit'])->toHaveKey('id');
    expect($row['unit']['id'])->toBe($unit->id);
    expect($row['unit']['community_id'])->toBe($community->id);
});

it('respects _per_page', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->count(7)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_per_page=3')
        ->assertOk();

    expect($resp->json('meta.per_page'))->toBe(3);
    expect(count($resp->json('data')))->toBe(3);
    expect($resp->json('meta.last_page'))->toBe(3);
});

it('paginates correctly across pages', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->count(5)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $page2 = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_per_page=2&page=2')
        ->assertOk();

    expect($page2->json('meta.current_page'))->toBe(2);
    expect(count($page2->json('data')))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters — community_id
// ──────────────────────────────────────────────────────────────────────────────

it('filters owners by community_id', function () {
    $user   = adminUser();
    $a      = Community::factory()->create(['organization_id' => $user->organization_id]);
    $b      = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unitA  = Unit::factory()->create(['community_id' => $a->id, 'organization_id' => $user->organization_id]);
    $unitB  = Unit::factory()->create(['community_id' => $b->id, 'organization_id' => $user->organization_id]);

    Owner::factory()->count(2)->create(['unit_id' => $unitA->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->count(3)->create(['unit_id' => $unitB->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . "?community_id={$a->id}")
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
    foreach ($resp->json('data') as $row) {
        expect($row['unit']['community_id'])->toBe($a->id);
    }
});

it('returns no results when filtering by an unknown community_id', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?community_id=00000000-0000-0000-0000-000000000000')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sort (_sort)
// ──────────────────────────────────────────────────────────────────────────────

it('sorts owners by full_name asc', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $n) {
        Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'full_name' => $n]);
    }

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_sort=full_name:asc')
        ->assertOk()
        ->json('data.*.full_name');

    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('sorts owners by full_name desc', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    foreach (['Charlie', 'Alpha', 'Bravo'] as $n) {
        Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'full_name' => $n]);
    }

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_sort=full_name:desc')
        ->assertOk()
        ->json('data.*.full_name');

    expect($names)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

it('sanitises malicious _sort column input — table is not dropped', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->count(2)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_sort=' . urlencode("name'); DROP TABLE owners; --:asc"));

    expect(\Illuminate\Support\Facades\DB::table('owners')->count())->toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Date range
// ──────────────────────────────────────────────────────────────────────────────

it('filters by _date_range=today', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()->subDays(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_date_range=today')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters by _date_range=this_month', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()->startOfMonth()->addDay()]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()->subMonths(2)]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_date_range=this_month')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters by _date_range=custom with start + end', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-02-15')]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-04-15')]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => Carbon::parse('2026-06-15')]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_date_range=custom&_date_range_start=2026-03-01&_date_range_end=2026-05-31')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('does not filter when _date_range=all_time', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()->subYears(5)]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'created_at' => now()]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_date_range=all_time')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Search (Postgres-only)
// ──────────────────────────────────────────────────────────────────────────────

it('searches owners by full_name / email / phone (Postgres ilike)', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'full_name' => 'Crystal']);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'full_name' => 'River']);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owners') . '?_search=Crystal')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/owners/{owner}  —  show                                          ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns a single owner with unit + community eager-loaded', function () {
    [$user, $community, $unit] = setupOwnerOccupant();
    $owner = Owner::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'full_name' => 'Joe Smith',
        'email'     => 'joe@example.com',
    ]);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $owner))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'full_name', 'email',
                'unit' => ['id', 'unit_number', 'community' => ['id', 'name']],
                'invoices',
            ],
        ])
        ->json();

    expect($body['data']['id'])->toBe($owner->id);
    expect($body['data']['full_name'])->toBe('Joe Smith');
    expect($body['data']['email'])->toBe('joe@example.com');
    expect($body['data']['unit']['community']['id'])->toBe($community->id);
});

it('includes the owner\'s billed invoices on the show payload', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    // Schema enforces unique (unit_id, ledger_id, billing_period) — vary billing_period.
    Invoice::factory()->create([
        'unit_id'        => $unit->id,
        'organization_id'      => $user->organization_id,
        'ledger_id' => $ledger->id,
        'billed_to_type' => 'owner',
        'billed_to_id'   => $owner->id,
        'billing_period' => '2026-01-01',
    ]);
    Invoice::factory()->create([
        'unit_id'        => $unit->id,
        'organization_id'      => $user->organization_id,
        'ledger_id' => $ledger->id,
        'billed_to_type' => 'owner',
        'billed_to_id'   => $owner->id,
        'billing_period' => '2026-02-01',
    ]);
    // Organization-billed invoice for the same unit must NOT show up on the owner.
    Invoice::factory()->create([
        'unit_id'        => $unit->id,
        'organization_id'      => $user->organization_id,
        'ledger_id' => $ledger->id,
        'billed_to_type' => 'occupant',
        'billed_to_id'   => $owner->id,
        'billing_period' => '2026-03-01',
    ]);

    $invoices = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $owner))
        ->assertOk()
        ->json('data.invoices');

    expect(count($invoices))->toBe(2);
});

it('returns 404 for an unknown owner id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', ['owner' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

it('does not expose hidden fields on the owner payload', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $owner))
        ->assertOk()
        ->json('data');

    expect($row)->not->toHaveKey('password');
    expect($row)->not->toHaveKey('remember_token');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/owners/{owner}  —  update                                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// Validation rules

it('rejects update with full_name > 255 chars', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['full_name' => str_repeat('z', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('rejects update when full_name is not a string', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['full_name' => ['nested']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('rejects update with invalid email format', function (string $bad) {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['email' => $bad])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
})->with([
    'plain text'    => ['notanemail'],
    'missing local' => ['@host.com'],
    'with spaces'   => ['has spaces@host.com'],
]);

it('rejects update with email > 255 chars', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $longEmail = str_repeat('a', 250) . '@x.com';

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['email' => $longEmail])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects update with phone > 30 chars', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['phone' => str_repeat('1', 31)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('rejects update with id_number > 50 chars', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['id_number' => str_repeat('x', 51)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['id_number']);
});

it('rejects update with address > 500 chars', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), ['address' => str_repeat('a', 501)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address']);
});

it('preserves existing values when nullable fields are explicitly nulled', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit, ['phone' => '+27 11 555', 'id_number' => '8001015009087', 'address' => '123 Main']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), [
            'phone'     => null,
            'id_number' => null,
            'address'   => null,
        ])
        ->assertOk();

    // The service filters out null values before update — the originals stay.
    $fresh = $owner->fresh();
    expect($fresh->phone)->toBe('+27 11 555');
    expect($fresh->id_number)->toBe('8001015009087');
    expect($fresh->address)->toBe('123 Main');
});

// Successful update

it('updates an owner with a partial payload — returns updated resource + message', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = Owner::factory()->create([
        'unit_id'   => $unit->id,
        'organization_id' => $user->organization_id,
        'full_name' => 'Old Name',
        'email'     => 'old@x.com',
        'phone'     => '+27 11 0000',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), [
            'full_name' => 'New Name',
            'email'     => 'new@x.com',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.full_name', 'New Name')
        ->assertJsonPath('data.email', 'new@x.com')
        ->assertJsonPath('data.phone', '+27 11 0000');

    $this->assertDatabaseHas('owners', [
        'id'        => $owner->id,
        'full_name' => 'New Name',
        'email'     => 'new@x.com',
        'phone'     => '+27 11 0000',
    ]);
});

it('does not let the client change organization_id or unit_id via update', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner       = makeOwner($user, $unit);
    $other       = createOrganization();
    $otherUnit   = Unit::factory()->create(['community_id' => Community::factory()->create(['organization_id' => $other->id])->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $owner), [
            'organization_id' => $other->id,
            'unit_id'   => $otherUnit->id,
            'full_name' => 'Updated',
        ])
        ->assertOk();

    $fresh = $owner->fresh();
    expect($fresh->organization_id)->toBe($user->organization_id);
    expect($fresh->unit_id)->toBe($unit->id);
    expect($fresh->full_name)->toBe('Updated');
});

it('returns 404 when updating an unknown owner id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', ['owner' => '00000000-0000-0000-0000-000000000000']), ['full_name' => 'X'])
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/owners/{owner}  —  single                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('deletes a single owner', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owner', $owner))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Owner deleted']);

    $this->assertDatabaseMissing('owners', ['id' => $owner->id]);
});

it('returns 404 when deleting an unknown owner id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owner', ['owner' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ DELETE /v1/owners  —  bulk                                               ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('bulk deletes own-occupant owners and pluralises the message', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owners = Owner::factory()->count(3)->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => $owners->pluck('id')->all()])
        ->assertOk()
        ->assertJson(['message' => '3 Owners deleted']);

    foreach ($owners as $o) {
        $this->assertDatabaseMissing('owners', ['id' => $o->id]);
    }
});

it('uses the singular label when bulk-deleting one', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => [$owner->id]])
        ->assertOk()
        ->assertJson(['message' => '1 Owner deleted']);
});

it('only deletes own-occupant owners when a mix of own + cross-occupant ids is supplied', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $own   = makeOwner($user, $unit);

    $other      = createOrganization();
    $otherUnit  = Unit::factory()->create(['community_id' => Community::factory()->create(['organization_id' => $other->id])->id, 'organization_id' => $other->id]);
    $otherOwner = Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => [$own->id, $otherOwner->id]])
        ->assertOk()
        ->assertJson(['message' => '1 Owner deleted']);

    $this->assertDatabaseMissing('owners', ['id' => $own->id]);
    $this->assertDatabaseHas('owners',     ['id' => $otherOwner->id]);
});

it('returns 500 when every supplied id is cross-occupant (no owners deleted)', function () {
    $user        = adminUser();
    $other       = createOrganization();
    $otherUnit   = Unit::factory()->create(['community_id' => Community::factory()->create(['organization_id' => $other->id])->id, 'organization_id' => $other->id]);
    $otherOwner  = Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => [$otherOwner->id]])
        ->assertStatus(500);

    $this->assertDatabaseHas('owners', ['id' => $otherOwner->id]);
});

it('rejects bulk delete with a non-uuid id', function () {
    [$user, $_, $unit] = setupOwnerOccupant();
    $owner = makeOwner($user, $unit);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => [$owner->id, 'not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_ids.1']);
});

it('rejects bulk delete when owner_ids is not an array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), ['owner_ids' => 'a-single-id'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_ids']);
});

it('returns 403 when bulk delete owner_ids is missing or empty (policy guard)', function (array $payload) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owners'), $payload)
        ->assertForbidden();
})->with([
    'missing' => [[]],
    'empty'   => [['owner_ids' => []]],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Cross-occupant isolation                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('cross-occupant owner show returns 404', function () {
    $user        = adminUser();
    $other       = createOrganization();
    $otherUnit   = Unit::factory()->create(['community_id' => Community::factory()->create(['organization_id' => $other->id])->id, 'organization_id' => $other->id]);
    $otherOwner  = Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.owner', $otherOwner))
        ->assertNotFound();
});

it('cross-occupant owner update returns 404', function () {
    $user        = adminUser();
    $other       = createOrganization();
    $otherUnit   = Unit::factory()->create(['community_id' => Community::factory()->create(['organization_id' => $other->id])->id, 'organization_id' => $other->id]);
    $otherOwner  = Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.owner', $otherOwner), ['full_name' => 'Hacked'])
        ->assertNotFound();
});

it('cross-occupant owner delete returns 404', function () {
    $user        = adminUser();
    $other       = createOrganization();
    $otherUnit   = Unit::factory()->create(['community_id' => Community::factory()->create(['organization_id' => $other->id])->id, 'organization_id' => $other->id]);
    $otherOwner  = Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.owner', $otherOwner))
        ->assertNotFound();
});
