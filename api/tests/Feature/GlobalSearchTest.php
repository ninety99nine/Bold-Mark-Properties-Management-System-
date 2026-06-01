<?php

use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Support\Str;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a user plus one record of each searchable model, each with a
 * unique token embedded in its name/number so tests don't bleed into each other.
 */
function makeSearchableData(string $token): array
{
    $user = adminUser();

    $estate = Estate::factory()->create([
        'organization_id' => $user->organization_id,
        'name'            => "Estate {$token}",
        'address'         => "99 {$token} Road",
    ]);

    $unit = Unit::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'unit_number'     => "U{$token}",
    ]);

    $owner = Owner::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'full_name'       => "Owner {$token}",
        'email'           => "owner{$token}@example.com",
    ]);

    $tenant = Tenant::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'full_name'       => "Tenant {$token}",
        'email'           => "tenant{$token}@example.com",
    ]);

    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);

    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'billing_period'  => '2026-03-01',
        'invoice_number'  => "INV-TEST-{$token}",
    ]);

    return compact('user', 'estate', 'unit', 'owner', 'tenant', 'invoice', 'token');
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth & validation
// ──────────────────────────────────────────────────────────────────────────────

it('global search returns 401 without auth', function () {
    $this->getJson(route('api.v1.global.search') . '?q=test')
        ->assertUnauthorized();
});

it('global search returns 422 when q param is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

it('global search returns 422 when q is an empty string', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . '?q=')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

it('global search returns 422 when q exceeds 100 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . '?q=' . str_repeat('a', 101))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Response structure
// ──────────────────────────────────────────────────────────────────────────────

it('global search response has estates, units, people, and invoices keys', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . '?q=nothing')
        ->assertOk()
        ->assertJsonStructure(['estates', 'units', 'people', 'invoices']);
});

it('global search returns empty collections when nothing matches', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . '?q=ZZZZNOMATCH99999')
        ->assertOk();

    expect($response->json('estates'))->toBeEmpty();
    expect($response->json('units'))->toBeEmpty();
    expect($response->json('people'))->toBeEmpty();
    expect($response->json('invoices'))->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Estate search
// ──────────────────────────────────────────────────────────────────────────────

it('global search finds an estate matching its name', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user, 'estate' => $estate] = makeSearchableData($token);

    $estates = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('estates');

    expect(array_column($estates, 'id'))->toContain($estate->id);
});

it('global search estate result has id, name, type, and units_count fields', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user] = makeSearchableData($token);

    $estate = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('estates.0');

    expect($estate)->toHaveKeys(['id', 'name', 'type', 'units_count']);
});

it('global search estate units_count reflects the correct count', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user, 'estate' => $estate] = makeSearchableData($token);
    // makeSearchableData already created 1 unit for this estate

    $result = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('estates.0');

    expect($result['units_count'])->toBe(1);
});

it('global search finds an estate matching its address', function () {
    $token  = strtoupper(Str::random(8));
    $user   = adminUser();
    $estate = Estate::factory()->create([
        'organization_id' => $user->organization_id,
        'name'            => 'Something Else',
        'address'         => "99 {$token} Street",
    ]);

    $estates = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('estates');

    expect(array_column($estates, 'id'))->toContain($estate->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Unit search
// ──────────────────────────────────────────────────────────────────────────────

it('global search finds a unit matching its unit_number', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user, 'unit' => $unit] = makeSearchableData($token);

    $units = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('units');

    expect(array_column($units, 'id'))->toContain($unit->id);
});

it('global search unit result has id, unit_number, estate_id, estate_name, owner_name', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user] = makeSearchableData($token);

    $unit = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('units.0');

    expect($unit)->toHaveKeys(['id', 'unit_number', 'estate_id', 'estate_name', 'owner_name']);
});

it('global search unit result owner_name is populated when an owner exists', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user, 'owner' => $owner] = makeSearchableData($token);

    $unit = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('units.0');

    expect($unit['owner_name'])->toBe($owner->full_name);
});

// ──────────────────────────────────────────────────────────────────────────────
// People search — owners
// ──────────────────────────────────────────────────────────────────────────────

it('global search finds an owner matching their full_name', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user, 'owner' => $owner] = makeSearchableData($token);

    $people = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q=Owner+{$token}")
        ->assertOk()
        ->json('people');

    $ids = array_column($people, 'id');
    expect($ids)->toContain($owner->id);
});

it('global search owner in people has role Owner', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user] = makeSearchableData($token);

    $people = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q=Owner+{$token}")
        ->assertOk()
        ->json('people');

    $owner = collect($people)->firstWhere('role', 'Owner');
    expect($owner)->not->toBeNull();
    expect($owner)->toHaveKeys(['id', 'name', 'role', 'context', 'estate_id', 'unit_id']);
});

it('global search finds an owner matching their email', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user, 'owner' => $owner] = makeSearchableData($token);

    $people = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$owner->email}")
        ->assertOk()
        ->json('people');

    expect(array_column($people, 'id'))->toContain($owner->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// People search — tenants
// ──────────────────────────────────────────────────────────────────────────────

it('global search finds a tenant matching their full_name', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user, 'tenant' => $tenant] = makeSearchableData($token);

    $people = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q=Tenant+{$token}")
        ->assertOk()
        ->json('people');

    $ids = array_column($people, 'id');
    expect($ids)->toContain($tenant->id);
});

it('global search tenant in people has role Organization', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user] = makeSearchableData($token);

    $people = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q=Tenant+{$token}")
        ->assertOk()
        ->json('people');

    $tenant = collect($people)->firstWhere('role', 'Organization');
    expect($tenant)->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// People search — users
// ──────────────────────────────────────────────────────────────────────────────

it('global search finds a system user matching their name', function () {
    $token   = strtoupper(Str::random(8));
    $user    = adminUser();
    // Directly set a searchable name on the user
    $user->update(['name' => "SysUser {$token}"]);

    $people = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q=SysUser+{$token}")
        ->assertOk()
        ->json('people');

    $found = collect($people)->firstWhere('id', $user->id);
    expect($found)->not->toBeNull();
    expect($found)->toHaveKeys(['id', 'name', 'role', 'context']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Invoice search
// ──────────────────────────────────────────────────────────────────────────────

it('global search finds an invoice matching its invoice_number', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user, 'invoice' => $invoice] = makeSearchableData($token);

    $invoices = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('invoices');

    expect(array_column($invoices, 'id'))->toContain($invoice->id);
});

it('global search invoice result has id, invoice_number, amount, status, unit_number, estate_name', function () {
    $token = strtoupper(Str::random(8));
    ['user' => $user] = makeSearchableData($token);

    $invoice = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('invoices.0');

    expect($invoice)->toHaveKeys(['id', 'invoice_number', 'amount', 'status', 'unit_number', 'estate_name']);
});

it('global search finds invoice by normalized number (no dashes)', function () {
    $token = strtoupper(Str::random(6));
    ['user' => $user, 'invoice' => $invoice] = makeSearchableData($token);

    // Search without the dashes — e.g. "INVTEST{token}" instead of "INV-TEST-{token}"
    $normalized = 'INVTEST' . $token;

    $invoices = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$normalized}")
        ->assertOk()
        ->json('invoices');

    expect(array_column($invoices, 'id'))->toContain($invoice->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Per-category result cap
// ──────────────────────────────────────────────────────────────────────────────

it('global search returns at most 5 estates', function () {
    $token = strtoupper(Str::random(8));
    $user  = adminUser();

    // Create 7 estates all matching the token
    Estate::factory()->count(7)->create([
        'organization_id' => $user->organization_id,
        'name'            => "Estate {$token}",
    ]);

    $estates = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('estates');

    expect(count($estates))->toBeLessThanOrEqual(5);
});

it('global search returns at most 5 invoices', function () {
    $token      = strtoupper(Str::random(6));
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    // Create 7 invoices with the token in the number
    // Use deterministic year-based periods to avoid collisions with clock-dependent dates
    for ($i = 1; $i <= 7; $i++) {
        Invoice::factory()->create([
            'organization_id' => $user->organization_id,
            'unit_id'         => $unit->id,
            'charge_type_id'  => $chargeType->id,
            'billed_to_type'  => 'owner',
            'billed_to_id'    => $owner->id,
            'billing_period'  => \Carbon\Carbon::create(2010 + $i, 1, 1)->toDateString(),
            'invoice_number'  => "INV-{$token}-" . str_pad($i, 4, '0', STR_PAD_LEFT),
        ]);
    }

    $invoices = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('invoices');

    expect(count($invoices))->toBeLessThanOrEqual(5);
});

// ──────────────────────────────────────────────────────────────────────────────
// Cross-tenant isolation — the most critical gap
// ──────────────────────────────────────────────────────────────────────────────

it('global search estates are scoped to the authenticated tenant', function () {
    $token = strtoupper(Str::random(8));

    // Other tenant creates an estate with the token name
    ['estate' => $otherEstate] = makeSearchableData($token);

    // My user searches for the same token — should get nothing
    $myUser = adminUser();

    $estates = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('estates');

    expect(array_column($estates, 'id'))->not->toContain($otherEstate->id);
});

it('global search units are scoped to the authenticated tenant', function () {
    $token = strtoupper(Str::random(8));
    ['unit' => $otherUnit] = makeSearchableData($token);

    $myUser = adminUser();

    $units = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('units');

    expect(array_column($units, 'id'))->not->toContain($otherUnit->id);
});

it('global search owners are scoped to the authenticated tenant', function () {
    $token = strtoupper(Str::random(8));
    ['owner' => $otherOwner] = makeSearchableData($token);

    $myUser = adminUser();

    $people = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.global.search') . "?q=Owner+{$token}")
        ->assertOk()
        ->json('people');

    expect(array_column($people, 'id'))->not->toContain($otherOwner->id);
});

it('global search tenants are scoped to the authenticated tenant', function () {
    $token = strtoupper(Str::random(8));
    ['tenant' => $otherTenant] = makeSearchableData($token);

    $myUser = adminUser();

    $people = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.global.search') . "?q=Tenant+{$token}")
        ->assertOk()
        ->json('people');

    expect(array_column($people, 'id'))->not->toContain($otherTenant->id);
});

it('global search invoices are scoped to the authenticated tenant', function () {
    $token = strtoupper(Str::random(8));
    ['invoice' => $otherInvoice] = makeSearchableData($token);

    $myUser = adminUser();

    $invoices = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.global.search') . "?q={$token}")
        ->assertOk()
        ->json('invoices');

    expect(array_column($invoices, 'id'))->not->toContain($otherInvoice->id);
});

it('global search users are scoped to the authenticated tenant', function () {
    $token     = strtoupper(Str::random(8));
    $otherUser = adminUser();
    $otherUser->update(['name' => "OtherSysUser {$token}"]);

    $myUser = adminUser();

    $people = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.global.search') . "?q=OtherSysUser+{$token}")
        ->assertOk()
        ->json('people');

    // Other org's user must NOT appear
    expect(array_column($people, 'id'))->not->toContain($otherUser->id);
});
