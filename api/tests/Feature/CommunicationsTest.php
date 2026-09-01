<?php

use App\Enums\CommunityMemberType;
use App\Models\Communication;
use App\Models\CommunicationRecipient;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Owner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function communicationSetup(): array
{
    $user      = adminUser();
    $community = Community::factory()->create([
        'organization_id' => $user->organization_id,
        'name'            => 'Lyndhurst Estate',
    ]);

    return [$user, $community];
}

function makeOwnerFor(\App\Models\User $user, Community $community, array $overrides = []): Owner
{
    return Owner::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'unit_id'         => null,
        'is_disabled'     => false,
    ], $overrides));
}

function makeDirector(\App\Models\User $user, Community $community, string $email): CommunityMember
{
    return CommunityMember::create([
        'name'                => 'Trustee ' . fake()->lastName(),
        'email'               => $email,
        'user_type'           => CommunityMemberType::DIRECTOR_TRUSTEE->value,
        'is_director_trustee' => true,
        'community_id'        => $community->id,
        'organization_id'     => $user->organization_id,
    ]);
}

function fakeImageUpload(string $name = 'header.png'): UploadedFile
{
    return UploadedFile::fake()->image($name, 600, 200);
}

// =============================================================================
// Unauthenticated access
// =============================================================================

it('blocks unauthenticated access to community communications', function (string $method, string $route): void {
    $this->{$method . 'Json'}(route($route, ['community' => '00000000-0000-0000-0000-000000000000']))
        ->assertUnauthorized();
})->with([
    ['get',  'api.v1.show.community.communications'],
    ['post', 'api.v1.send.community.communication'],
    ['get',  'api.v1.show.community.email.settings'],
]);

// =============================================================================
// Send
// =============================================================================

it('sends a communication and resolves the selected recipient groups', function (): void {
    [$user, $community] = communicationSetup();
    makeOwnerFor($user, $community, ['full_name' => 'Owner One', 'email' => 'owner1@example.com']);
    makeOwnerFor($user, $community, ['full_name' => 'Owner Two', 'email' => 'owner2@example.com']);
    makeDirector($user, $community, 'trustee@example.com');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.send.community.communication', ['community' => $community->id]), [
            'recipient_groups' => ['owners', 'directors'],
            'subject'          => 'Notice',
            'body'             => '<p>Hello residents</p>',
        ])
        ->assertOk()
        ->assertJsonPath('data.recipient_count', 3)
        ->assertJsonPath('data.sent_count', 3)
        ->assertJsonPath('data.status', 'sent');

    $communication = Communication::where('community_id', $community->id)->first();
    expect($communication)->not->toBeNull();
    expect($communication->recipients)->toHaveCount(3);
    expect($communication->recipients->pluck('recipient_email')->sort()->values()->all())
        ->toEqual(['owner1@example.com', 'owner2@example.com', 'trustee@example.com']);
});

it('de-duplicates recipients across overlapping groups and includes secondary emails', function (): void {
    [$user, $community] = communicationSetup();
    makeOwnerFor($user, $community, [
        'email'            => 'primary@example.com',
        'secondary_emails' => ['secondary@example.com', 'primary@example.com'],
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.send.community.communication', ['community' => $community->id]), [
            'recipient_groups' => ['owners'],
            'subject'          => 'Hi',
            'body'             => 'x',
        ])
        ->assertOk()
        ->assertJsonPath('data.recipient_count', 2); // primary + secondary, duplicate dropped
});

it('rejects a send with no recipient groups', function (): void {
    [$user, $community] = communicationSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.send.community.communication', ['community' => $community->id]), [
            'subject' => 'Hi',
            'body'    => 'x',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipient_groups');
});

// =============================================================================
// Archive
// =============================================================================

it('lists sent communications for the community, newest first', function (): void {
    [$user, $community] = communicationSetup();
    makeOwnerFor($user, $community);

    $this->actingAs($user, 'api')->postJson(
        route('api.v1.send.community.communication', ['community' => $community->id]),
        ['recipient_groups' => ['owners'], 'subject' => 'First', 'body' => 'a']
    );

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.communications', ['community' => $community->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['subject'])->toBe('First');
});

// =============================================================================
// Resend
// =============================================================================

it('resends a previously sent communication', function (): void {
    [$user, $community] = communicationSetup();
    makeOwnerFor($user, $community);

    $send = $this->actingAs($user, 'api')->postJson(
        route('api.v1.send.community.communication', ['community' => $community->id]),
        ['recipient_groups' => ['owners'], 'subject' => 'Repeat', 'body' => 'a']
    )->json('data');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.community.communication', [
            'community'        => $community->id,
            'communicationLog' => $send['id'],
        ]))
        ->assertOk()
        ->assertJsonPath('data.status', 'sent');
});

// =============================================================================
// Email settings (Communicate → Settings)
// =============================================================================

it('uploads community email header and footer images', function (): void {
    Storage::fake('public');
    [$user, $community] = communicationSetup();

    $this->actingAs($user, 'api')
        ->post(route('api.v1.update.community.email.settings', ['community' => $community->id]), [
            'email_header' => fakeImageUpload('header.png'),
            'email_footer' => fakeImageUpload('footer.png'),
        ])
        ->assertOk();

    $community->refresh();
    expect($community->email_header_url)->not->toBeNull();
    expect($community->email_footer_url)->not->toBeNull();
});

it('removes a community email header image', function (): void {
    Storage::fake('public');
    [$user, $community] = communicationSetup();
    $community->update(['email_header_url' => 'http://localhost/storage/x.png']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.update.community.email.settings', ['community' => $community->id]), [
            'remove_email_header' => true,
        ])
        ->assertOk();

    expect($community->fresh()->email_header_url)->toBeNull();
});

// =============================================================================
// Webhook — delivery status
// =============================================================================

it('updates a communication recipient status from the resend webhook', function (): void {
    [$user, $community] = communicationSetup();
    $communication = Communication::create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'from_email'      => 'noreply@example.com',
        'subject'         => 'Hi',
        'body'            => 'x',
        'type'            => 'mail',
        'status'          => 'sent',
        'recipient_count' => 1,
        'sent_count'      => 1,
    ]);
    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'recipient_email'  => 'r@example.com',
        'status'           => 'sent',
        'resend_email_id'  => 'resend-abc-123',
        'view_token'       => \Illuminate\Support\Str::uuid(),
    ]);

    $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.opened',
        'data' => ['email_id' => 'resend-abc-123'],
    ])->assertOk();

    expect($recipient->fresh()->status)->toBe('read');
});

// =============================================================================
// Public download email-view
// =============================================================================

it('renders the public download email-view for a recipient token', function (): void {
    [$user, $community] = communicationSetup();
    makeOwnerFor($user, $community, ['full_name' => 'Owner One', 'email' => 'owner1@example.com']);

    $this->actingAs($user, 'api')->postJson(
        route('api.v1.send.community.communication', ['community' => $community->id]),
        ['recipient_groups' => ['owners'], 'subject' => 'Clearance Certificate', 'body' => '<p>Please see attached.</p>']
    );

    $recipient = CommunicationRecipient::first();

    $this->get(route('communications.view', ['token' => $recipient->view_token]))
        ->assertOk()
        ->assertSee('Clearance Certificate')
        ->assertSee('owner1@example.com');
});

it('returns 404 for an unknown view token', function (): void {
    $this->get(route('communications.view', ['token' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});
