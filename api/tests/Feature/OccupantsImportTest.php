<?php

use App\Models\Community;
use App\Models\Occupant;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function importCommunity(\App\Models\User $user, array $overrides = []): Community
{
    return Community::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'entity_type'     => 'residential_rental',
    ], $overrides));
}

function importUnit(Community $community, array $overrides = []): Unit
{
    return Unit::factory()->create(array_merge([
        'community_id'    => $community->id,
        'organization_id' => $community->organization_id,
    ], $overrides));
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Auth                                                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on occupants-import routes when unauthenticated', function (string $method, string $route) {
    $params = ['community' => '00000000-0000-0000-0000-000000000000'];
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',  'api.v1.occupants.import.template'],
    ['post', 'api.v1.occupants.import.parse'],
    ['post', 'api.v1.occupants.import'],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Template + parse                                                          ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('downloads an occupants CSV template by default', function () {
    $user      = adminUser();
    $community = importCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->get(route('api.v1.occupants.import.template', $community));

    $resp->assertOk();
    expect($resp->headers->get('Content-Type'))->toContain('text/csv');
    expect($resp->headers->get('Content-Disposition'))->toContain('occupants-import-template.csv');
    expect($resp->getContent())->toContain('occupant_full_name');
});

it('parses an uploaded occupants CSV into columns + rows', function () {
    $user      = adminUser();
    $community = importCommunity($user);

    $csv  = "unit_number,occupant_full_name,occupant_email\n";
    $csv .= "A01,Jane Doe,jane@x.com\n";
    $file = UploadedFile::fake()->createWithContent('occupants.csv', $csv);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.occupants.import.parse', $community), ['file' => $file])
        ->assertOk();

    expect($resp->json('columns'))->toContain('unit_number', 'occupant_full_name', 'occupant_email');
    expect($resp->json('total_rows'))->toBe(1);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Import                                                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('imports occupants and attaches them to matching units by unit_number', function () {
    $user      = adminUser();
    $community = importCommunity($user);
    $unit      = importUnit($community, ['unit_number' => 'A01', 'occupancy_type' => 'vacant']);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.occupants.import', $community), [
            'rows' => [
                ['unit_number' => 'A01', 'occupant_full_name' => 'Jane Doe', 'occupant_email' => 'jane@x.com'],
            ],
        ])
        ->assertOk();

    expect($resp->json('imported'))->toBe(1);

    $occupant = Occupant::where('unit_id', $unit->id)->where('is_active', true)->first();
    expect($occupant)->not->toBeNull();
    expect($occupant->full_name)->toBe('Jane Doe');
    expect($unit->fresh()->occupancy_type->value)->toBe('occupant_occupied');
});

it('updates the active occupant instead of creating a duplicate', function () {
    $user      = adminUser();
    $community = importCommunity($user);
    $unit      = importUnit($community, ['unit_number' => 'A01', 'occupancy_type' => 'occupant_occupied']);
    Occupant::factory()->create([
        'unit_id'         => $unit->id,
        'organization_id' => $community->organization_id,
        'full_name'       => 'Old Tenant',
        'is_active'       => true,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.occupants.import', $community), [
            'rows' => [
                ['unit_number' => 'A01', 'occupant_full_name' => 'New Tenant', 'occupant_email' => 'new@x.com'],
            ],
        ])
        ->assertOk();

    expect(Occupant::where('unit_id', $unit->id)->where('is_active', true)->count())->toBe(1);
    expect($unit->fresh()->currentOccupant->full_name)->toBe('New Tenant');
});

it('skips rows whose unit cannot be matched', function () {
    $user      = adminUser();
    $community = importCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.occupants.import', $community), [
            'rows' => [
                ['unit_number' => 'NOPE', 'occupant_full_name' => 'Ghost', 'occupant_email' => 'ghost@x.com'],
            ],
        ])
        ->assertOk();

    expect($resp->json('imported'))->toBe(0);
    expect($resp->json('skipped'))->toBe(1);
});

it('matches units by customer_code when unit_number is absent', function () {
    $user      = adminUser();
    $community = importCommunity($user);
    $unit      = importUnit($community, ['unit_number' => 'A01', 'customer_code' => 'ATL001-D1', 'occupancy_type' => 'vacant']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.occupants.import', $community), [
            'rows' => [
                ['customer_code' => 'ATL001-D1', 'occupant_full_name' => 'Jane Doe', 'occupant_email' => 'jane@x.com'],
            ],
        ])
        ->assertOk();

    expect(Occupant::where('unit_id', $unit->id)->where('is_active', true)->exists())->toBeTrue();
});
