<?php

use App\Models\Community;
use App\Models\Ledger;
use App\Models\SplitTemplate;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on split template routes when unauthenticated', function (string $method, string $route) {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->{$method . 'Json'}(route($route, ['community' => $community->id]))->assertUnauthorized();
})->with([
    ['get',  'api.v1.show.split.templates'],
    ['post', 'api.v1.create.split.template'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /communities/{community}/split-templates
// ──────────────────────────────────────────────────────────────────────────────

it('lists split templates scoped to the community', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $other     = Community::factory()->create(['organization_id' => $user->organization_id]);

    SplitTemplate::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    SplitTemplate::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    SplitTemplate::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $other->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.split.templates', ['community' => $community->id]))
        ->assertOk()
        ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'lines', 'lines_count']]]);

    expect($response->json('data'))->toHaveCount(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /communities/{community}/split-templates
// ──────────────────────────────────────────────────────────────────────────────

it('creates a split template for a community', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.split.template', ['community' => $community->id]), [
            'name'  => 'Levy + Interest',
            'lines' => [
                ['ledger_type' => 'general', 'ledger_id' => $ledger->id, 'amount' => 800],
                ['ledger_type' => 'general', 'ledger_id' => $ledger->id, 'amount' => 200],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Levy + Interest')
        ->assertJsonPath('data.lines_count', 2);

    $this->assertDatabaseHas('split_templates', [
        'community_id' => $community->id, 'name' => 'Levy + Interest',
    ]);
});

it('requires a name and at least one line', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.split.template', ['community' => $community->id]), [
            'name'  => '',
            'lines' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'lines']);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET / DELETE single
// ──────────────────────────────────────────────────────────────────────────────

it('shows a single split template', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $template  = SplitTemplate::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.split.template', ['community' => $community->id, 'splitTemplate' => $template->id]))
        ->assertOk()
        ->assertJsonPath('data.id', $template->id);
});

it('deletes a split template', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $template  = SplitTemplate::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.split.template', ['community' => $community->id, 'splitTemplate' => $template->id]))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertDatabaseMissing('split_templates', ['id' => $template->id]);
});

it('returns 404 for a split template from another organisation', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $otherOrg  = createOrganization();
    $otherComm = Community::factory()->create(['organization_id' => $otherOrg->id]);
    $template  = SplitTemplate::factory()->create([
        'organization_id' => $otherOrg->id, 'community_id' => $otherComm->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.split.template', ['community' => $community->id, 'splitTemplate' => $template->id]))
        ->assertNotFound();
});
