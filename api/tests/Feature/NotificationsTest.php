<?php

use App\Models\Community;
use App\Notifications\BillingRunCompleted;
use Illuminate\Support\Str;

// ──────────────────────────────────────────────────────────────────────────────
// Helper
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a user who already has one database notification.
 * Returns ['user', 'community', 'notification'].
 */
function makeNotifiedUser(): array
{
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $user->notify(new BillingRunCompleted($community, 3, 'April 2026'));
    $notification = $user->notifications()->first();

    return compact('user', 'community', 'notification');
}

// ──────────────────────────────────────────────────────────────────────────────
// index — GET /notifications
// ──────────────────────────────────────────────────────────────────────────────

it('notifications index returns 401 without auth', function () {
    $this->getJson(route('api.v1.show.notifications'))
        ->assertUnauthorized();
});

it('notifications index returns data and unread_count keys', function () {
    ['user' => $user] = makeNotifiedUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk()
        ->assertJsonStructure(['data', 'unread_count']);
});

it('notifications index returns an empty list when the user has no notifications', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
    expect($response->json('unread_count'))->toBe(0);
});

it('notifications index returns a notification in the data list', function () {
    ['user' => $user] = makeNotifiedUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('notifications index each entry has id, type, data, read_at, created_at', function () {
    ['user' => $user] = makeNotifiedUser();

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk()
        ->json('data.0');

    expect($item)->toHaveKeys(['id', 'type', 'data', 'read_at', 'created_at']);
});

it('notifications index type is the class basename of the notification', function () {
    ['user' => $user] = makeNotifiedUser();

    $type = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk()
        ->json('data.0.type');

    expect($type)->toBe('BillingRunCompleted');
});

it('notifications index data contains the notification payload', function () {
    ['user' => $user, 'community' => $community] = makeNotifiedUser();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk()
        ->json('data.0.data');

    expect($data)->toHaveKeys(['community_id', 'community_name', 'invoice_count', 'billing_period', 'message']);
    expect($data['community_id'])->toBe($community->id);
    expect($data['invoice_count'])->toBe(3);
    expect($data['billing_period'])->toBe('April 2026');
});

it('notifications index read_at is null for an unread notification', function () {
    ['user' => $user] = makeNotifiedUser();

    $readAt = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk()
        ->json('data.0.read_at');

    expect($readAt)->toBeNull();
});

it('notifications index unread_count equals the number of unread notifications', function () {
    ['user' => $user, 'community' => $community] = makeNotifiedUser();
    // Add a second notification
    $user->notify(new BillingRunCompleted($community, 5, 'May 2026'));

    $unreadCount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk()
        ->json('unread_count');

    expect($unreadCount)->toBe(2);
});

it('notifications index unread_count is 0 when all notifications are already read', function () {
    ['user' => $user] = makeNotifiedUser();
    $user->unreadNotifications->markAsRead();

    $unreadCount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk()
        ->json('unread_count');

    expect($unreadCount)->toBe(0);
});

it('notifications index does not return other users notifications', function () {
    // Another user gets a notification
    $otherUser = adminUser();
    $community    = Community::factory()->create(['organization_id' => $otherUser->organization_id]);
    $otherUser->notify(new BillingRunCompleted($community, 2, 'March 2026'));

    // My user has zero notifications
    $myUser = adminUser();

    $response = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
    expect($response->json('unread_count'))->toBe(0);
});

it('notifications index caps results at 30', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    for ($i = 0; $i < 35; $i++) {
        $user->notify(new BillingRunCompleted($community, $i, 'Jan 2026'));
    }

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->assertOk()
        ->json('data');

    expect(count($data))->toBe(30);
});

// ──────────────────────────────────────────────────────────────────────────────
// markAsRead — POST /notifications/{notificationId}/mark-read
// ──────────────────────────────────────────────────────────────────────────────

it('mark as read returns 401 without auth', function () {
    $this->postJson(route('api.v1.mark.notification.read', Str::uuid()))
        ->assertUnauthorized();
});

it('mark as read sets read_at on the notification', function () {
    ['user' => $user, 'notification' => $notification] = makeNotifiedUser();
    expect($notification->read_at)->toBeNull();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.mark.notification.read', $notification->id))
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('mark as read returns a success message', function () {
    ['user' => $user, 'notification' => $notification] = makeNotifiedUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.mark.notification.read', $notification->id))
        ->assertOk()
        ->assertJson(['message' => 'Notification marked as read']);
});

it('mark as read returns 404 for a non-existent notification id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.mark.notification.read', Str::uuid()))
        ->assertNotFound();
});

it('mark as read returns 404 when trying to read another users notification', function () {
    // Other user gets a notification
    $otherUser = adminUser();
    $community    = Community::factory()->create(['organization_id' => $otherUser->organization_id]);
    $otherUser->notify(new BillingRunCompleted($community, 1, 'Feb 2026'));
    $otherNotification = $otherUser->notifications()->first();

    $myUser = adminUser();

    // My user cannot mark the other user's notification as read
    $this->actingAs($myUser, 'api')
        ->postJson(route('api.v1.mark.notification.read', $otherNotification->id))
        ->assertNotFound();

    // The other user's notification is still unread
    expect($otherNotification->fresh()->read_at)->toBeNull();
});

it('unread_count decrements by one after marking a notification as read', function () {
    ['user' => $user, 'community' => $community, 'notification' => $notification] = makeNotifiedUser();
    $user->notify(new BillingRunCompleted($community, 2, 'May 2026'));

    // Initially 2 unread
    $before = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->json('unread_count');
    expect($before)->toBe(2);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.mark.notification.read', $notification->id))
        ->assertOk();

    $after = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->json('unread_count');
    expect($after)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// markAllAsRead — POST /notifications/mark-all-read
// ──────────────────────────────────────────────────────────────────────────────

it('mark all as read returns 401 without auth', function () {
    $this->postJson(route('api.v1.mark.all.notifications.read'))
        ->assertUnauthorized();
});

it('mark all as read sets read_at on all unread notifications', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $user->notify(new BillingRunCompleted($community, 1, 'Jan 2026'));
    $user->notify(new BillingRunCompleted($community, 2, 'Feb 2026'));

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.mark.all.notifications.read'))
        ->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
    $user->notifications()->each(fn ($n) => expect($n->read_at)->not->toBeNull());
});

it('mark all as read returns a success message', function () {
    ['user' => $user] = makeNotifiedUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.mark.all.notifications.read'))
        ->assertOk()
        ->assertJson(['message' => 'All notifications marked as read']);
});

it('mark all as read only affects the authenticated users notifications', function () {
    // Other user has an unread notification
    $otherUser = adminUser();
    $community    = Community::factory()->create(['organization_id' => $otherUser->organization_id]);
    $otherUser->notify(new BillingRunCompleted($community, 5, 'Mar 2026'));

    // My user marks all their own as read (they have none, but the call should not touch other users)
    $myUser = adminUser();
    $this->actingAs($myUser, 'api')
        ->postJson(route('api.v1.mark.all.notifications.read'))
        ->assertOk();

    // The other user's notification is still unread
    expect($otherUser->unreadNotifications()->count())->toBe(1);
});

it('unread_count is 0 after marking all notifications as read', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $user->notify(new BillingRunCompleted($community, 1, 'Jan 2026'));
    $user->notify(new BillingRunCompleted($community, 2, 'Feb 2026'));

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.mark.all.notifications.read'))
        ->assertOk();

    $unreadCount = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.notifications'))
        ->json('unread_count');

    expect($unreadCount)->toBe(0);
});

it('mark all as read is idempotent when all notifications are already read', function () {
    ['user' => $user] = makeNotifiedUser();
    $user->unreadNotifications->markAsRead();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.mark.all.notifications.read'))
        ->assertOk()
        ->assertJson(['message' => 'All notifications marked as read']);
});
