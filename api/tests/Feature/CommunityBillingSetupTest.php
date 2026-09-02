<?php

use App\Models\Community;
use App\Models\CommunityBillingSetup;
use App\Models\Ledger;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on billing setup routes when unauthenticated', function (string $method, string $name) {
    $this->{$method . 'Json'}(route($name, ['community' => 'non-existent']))
        ->assertUnauthorized();
})->with([
    ['get', 'api.v1.community.billing.setup.show'],
    ['put', 'api.v1.community.billing.setup.update'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET — defaults
// ──────────────────────────────────────────────────────────────────────────────

it('returns a default billing setup, creating it on first access', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    expect(CommunityBillingSetup::where('community_id', $community->id)->exists())->toBeFalse();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.billing.setup.show', ['community' => $community->id]))
        ->assertOk()
        ->assertJsonPath('data.community_id', $community->id)
        ->assertJsonPath('data.levies_ledger_id', null)
        ->assertJsonPath('data.apply_csos_levy', true)
        ->assertJsonPath('data.occupant_billing', false);

    expect(CommunityBillingSetup::where('community_id', $community->id)->count())->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT — update
// ──────────────────────────────────────────────────────────────────────────────

it('updates charge to ledger mappings and toggles', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $levies    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $water     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.community.billing.setup.update', ['community' => $community->id]), [
            'levies_ledger_id'   => $levies->id,
            'water_ledger_id'    => $water->id,
            'ratio_1_ledger_id'  => $levies->id,
            'ratio_1_csos_exempt' => true,
            'apply_csos_levy'    => false,
            'water_recovery'     => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.levies_ledger_id', $levies->id)
        ->assertJsonPath('data.water_ledger_id', $water->id)
        ->assertJsonPath('data.ratio_1_csos_exempt', true)
        ->assertJsonPath('data.apply_csos_levy', false)
        ->assertJsonPath('data.water_recovery', true);

    $setup = CommunityBillingSetup::where('community_id', $community->id)->firstOrFail();
    expect($setup->levies_ledger_id)->toBe($levies->id)
        ->and($setup->apply_csos_levy)->toBeFalse()
        ->and($setup->water_recovery)->toBeTrue();
});

it('rejects a mapping to a non-existent ledger', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.community.billing.setup.update', ['community' => $community->id]), [
            'levies_ledger_id' => '01a00000-0000-7000-8000-000000000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['levies_ledger_id']);
});

it('cannot access the billing setup of another organization', function () {
    $user  = adminUser();
    $other = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $other->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.billing.setup.show', ['community' => $otherCommunity->id]))
        ->assertNotFound();
});
