<?php

use App\Models\Community;
use App\Models\JournalGroup;
use Database\Seeders\JournalGroupSeeder;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A user with a plain role (no super/company-admin) and no journal permissions.
 * Journal-group create/delete gate on the journal.* permission strings, so we
 * register them (guard: api) to make the permission check resolve to "not granted".
 */
function journalGroupRestrictedUser(): \App\Models\User
{
    foreach (['journal.create', 'journal.update', 'journal.delete'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
    }

    return createUser(createOrganization(), 'community-manager');
}

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on every journal-group route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.journal.groups',  ['community' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.create.journal.group', ['community' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.show.journal.group',   ['community' => '00000000-0000-0000-0000-000000000000', 'journalGroup' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.journal.group', ['community' => '00000000-0000-0000-0000-000000000000', 'journalGroup' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.journal.group', ['community' => '00000000-0000-0000-0000-000000000000', 'journalGroup' => '00000000-0000-0000-0000-000000000000']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/communities/{community}/journal-groups — index
// ──────────────────────────────────────────────────────────────────────────────

// JournalGroupPolicy::viewAny() → Tier 1 (return true) — no 403 test needed.

it('returns the journal group list scoped to the organization and community', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $other     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $otherOrg  = createOrganization();

    JournalGroup::factory()->count(3)->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    JournalGroup::factory()->count(2)->create(['organization_id' => $user->organization_id, 'community_id' => $other->id]);
    JournalGroup::factory()->create(['organization_id' => $otherOrg->id, 'community_id' => Community::factory()->create(['organization_id' => $otherOrg->id])->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.journal.groups', $community))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'community_id', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($resp->json('meta.total'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /v1/communities/{community}/journal-groups — create
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from creating a journal group', function () {
    $user      = journalGroupRestrictedUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.group', $community), ['name' => 'Sundry'])
        ->assertForbidden();
});

it('requires a name to create a journal group', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.group', $community), ['name' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('creates a journal group', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.group', $community), ['name' => 'Sundry'])
        ->assertOk()
        ->assertJsonPath('message', 'Created successfully')
        ->assertJsonPath('data.name', 'Sundry');

    $this->assertDatabaseHas('journal_groups', [
        'name'            => 'Sundry',
        'community_id'    => $community->id,
        'organization_id' => $user->organization_id,
    ]);
});

it('rejects a duplicate journal group name in the same community', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    JournalGroup::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'name' => 'Levy']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.group', $community), ['name' => 'Levy'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/communities/{community}/journal-groups/{journalGroup} — show
// ──────────────────────────────────────────────────────────────────────────────

// JournalGroupPolicy::view() → Tier 1 (return true) — no 403 test needed.

it('returns a single journal group', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $group     = JournalGroup::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'name' => 'Audit']);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.journal.group', [$community, $group]))
        ->assertOk()
        ->assertJsonPath('data.id', $group->id)
        ->assertJsonPath('data.name', 'Audit');
});

it('returns 404 for a cross-organization journal group', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $otherOrg  = createOrganization();
    $group     = JournalGroup::factory()->create(['organization_id' => $otherOrg->id, 'community_id' => Community::factory()->create(['organization_id' => $otherOrg->id])->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.journal.group', [$community, $group]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /v1/communities/{community}/journal-groups/{journalGroup} — delete
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from deleting a journal group', function () {
    $user      = journalGroupRestrictedUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $group     = JournalGroup::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.journal.group', [$community, $group]))
        ->assertForbidden();
});

it('deletes a journal group', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $group     = JournalGroup::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.journal.group', [$community, $group]))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Journal group deleted']);

    $this->assertDatabaseMissing('journal_groups', ['id' => $group->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Seeder — 11 default journal groups per community
// ──────────────────────────────────────────────────────────────────────────────

it('seeds the 11 default journal groups for a community', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    (new JournalGroupSeeder())->seedForCommunity($community);

    $names = JournalGroup::where('community_id', $community->id)->pluck('name')->sort()->values()->all();

    expect($names)->toBe(collect(JournalGroupSeeder::DEFAULTS)->sort()->values()->all());
    expect(JournalGroup::where('community_id', $community->id)->count())->toBe(11);
});
