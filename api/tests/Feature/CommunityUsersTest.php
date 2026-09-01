<?php

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Owner;
use App\Models\Unit;

function cuCommunity(\App\Models\User $user): Community
{
    return Community::factory()->create([
        'organization_id' => $user->organization_id,
        'entity_type'     => 'residential_rental',
    ]);
}

function cuOwner(Community $community, array $overrides = []): Owner
{
    $unit = Unit::factory()->create([
        'community_id'    => $community->id,
        'organization_id' => $community->organization_id,
    ]);

    return Owner::factory()->create(array_merge([
        'unit_id'         => $unit->id,
        'organization_id' => $community->organization_id,
    ], $overrides));
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Auth                                                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on community user routes when unauthenticated', function () {
    $this->getJson(route('api.v1.community.users.index', ['community' => '00000000-0000-0000-0000-000000000000']))
        ->assertUnauthorized();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Listing + owner sync                                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('lists community users and auto-syncs owners as members', function () {
    $user = adminUser(); $community = cuCommunity($user);
    cuOwner($community, ['full_name' => 'Amanda Chaumbezvo', 'email' => 'amanda@x.com', 'user_verified' => true]);
    cuOwner($community, ['full_name' => 'Jane Nkosi', 'email' => 'jane@x.com', 'user_verified' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.users.index', ['community' => $community]))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
    $amanda = collect($resp->json('data'))->firstWhere('name', 'Amanda Chaumbezvo');
    expect($amanda['type_label'])->toBe('Owner');
    expect($amanda['is_verified'])->toBeTrue();
});

it('does not duplicate owner members on repeated listing', function () {
    $user = adminUser(); $community = cuCommunity($user);
    cuOwner($community, ['full_name' => 'Amanda', 'email' => 'amanda@x.com']);

    $this->actingAs($user, 'api')->getJson(route('api.v1.community.users.index', ['community' => $community]))->assertOk();
    $this->actingAs($user, 'api')->getJson(route('api.v1.community.users.index', ['community' => $community]))->assertOk();

    expect(CommunityMember::where('community_id', $community->id)->count())->toBe(1);
});

it('filters users by type', function () {
    $user = adminUser(); $community = cuCommunity($user);
    CommunityMember::create(['name' => 'Boss', 'user_type' => 'complex_manager', 'community_id' => $community->id, 'organization_id' => $community->organization_id]);
    cuOwner($community, ['full_name' => 'Owner Guy', 'email' => 'o@x.com']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.users.index', ['community' => $community, 'filter' => 'complex_managers']))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.type_label'))->toBe('Complex Manager');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ CRUD                                                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('adds a community user', function () {
    $user = adminUser(); $community = cuCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.community.users.store', ['community' => $community]), [
            'name' => 'Boss Manager', 'email' => 'boss@x.com', 'cellphone' => '063 370 2841',
            'user_type' => 'complex_manager',
        ])
        ->assertOk();

    expect($resp->json('data.type_label'))->toBe('Complex Manager');
});

it('derives Owner/Trustee type when the director flag is set', function () {
    $user = adminUser(); $community = cuCommunity($user);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.community.users.store', ['community' => $community]), [
            'name' => 'Trustee Owner', 'email' => 't@x.com', 'user_type' => 'owner', 'is_director_trustee' => true,
        ])
        ->assertOk();

    expect($resp->json('data.type_label'))->toBe('Owner/Trustee');
});

it('requires a name and valid user_type to add a user', function () {
    $user = adminUser(); $community = cuCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.community.users.store', ['community' => $community]), ['user_type' => 'nonsense'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'user_type']);
});

it('updates a community user', function () {
    $user = adminUser(); $community = cuCommunity($user);
    $m = CommunityMember::create(['name' => 'Old', 'user_type' => 'owner', 'community_id' => $community->id, 'organization_id' => $community->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.community.users.update', ['community' => $community, 'member' => $m]), ['name' => 'New Name'])
        ->assertOk();

    expect($m->fresh()->name)->toBe('New Name');
});

it('removes a community user', function () {
    $user = adminUser(); $community = cuCommunity($user);
    $m = CommunityMember::create(['name' => 'Bye', 'user_type' => 'owner', 'community_id' => $community->id, 'organization_id' => $community->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.community.users.destroy', ['community' => $community, 'member' => $m]))
        ->assertOk();

    expect(CommunityMember::find($m->id))->toBeNull();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Bulk director/trustee + payment authorisation                            ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('sets directors/trustees in bulk', function () {
    $user = adminUser(); $community = cuCommunity($user);
    $a = CommunityMember::create(['name' => 'A', 'user_type' => 'owner', 'community_id' => $community->id, 'organization_id' => $community->organization_id]);
    $b = CommunityMember::create(['name' => 'B', 'user_type' => 'owner', 'is_director_trustee' => true, 'community_id' => $community->id, 'organization_id' => $community->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.community.users.directors', ['community' => $community]), ['member_ids' => [$a->id]])
        ->assertOk();

    expect($a->fresh()->is_director_trustee)->toBeTrue();
    expect($b->fresh()->is_director_trustee)->toBeFalse();
});

it('sets payment authorisers and the authorisation mode', function () {
    $user = adminUser(); $community = cuCommunity($user);
    $a = CommunityMember::create(['name' => 'A', 'user_type' => 'director_trustee', 'community_id' => $community->id, 'organization_id' => $community->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.community.users.authorisations', ['community' => $community]), ['member_ids' => [$a->id], 'mode' => 'two'])
        ->assertOk();

    expect($a->fresh()->is_payment_authoriser)->toBeTrue();
    expect($community->fresh()->payment_authorisation_mode->value)->toBe('two');
});

it('rejects an invalid authorisation mode', function () {
    $user = adminUser(); $community = cuCommunity($user);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.community.users.authorisations', ['community' => $community]), ['member_ids' => [], 'mode' => 'nonsense'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mode']);
});
