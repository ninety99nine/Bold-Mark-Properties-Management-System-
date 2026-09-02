<?php

use App\Models\CashbookEntry;
use App\Models\Ledger;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\InvoiceEmailEvent;
use App\Models\Owner;
use App\Models\Unit;
use Resend\Laravel\Facades\Resend;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Build a fully-wired invoice billed to an owner with an email address.
 * Returns ['user', 'community', 'unit', 'owner', 'ledger', 'invoice'].
 */
function makeInvoiceForEmail(array $ownerOverrides = [], array $invoiceOverrides = []): array
{
    $user       = adminUser();
    $community     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(array_merge([
        'unit_id'         => $unit->id,
        'organization_id' => $user->organization_id,
        'email'           => 'owner@example.com',
    ], $ownerOverrides));
    $invoice = Invoice::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ledger->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'amount'          => 1500,
    ], $invoiceOverrides));

    return compact('user', 'community', 'unit', 'owner', 'ledger', 'invoice');
}

/**
 * Return a Mockery mock chain that stubs Resend::emails()->send() and returns
 * a fake \Resend\Email response with the given resend_email_id.
 */
function fakeResendSend(string $fakeId = 'fake-resend-id-001'): void
{
    $fakeEmail  = \Resend\Email::from(['id' => $fakeId]);
    $emailsMock = Mockery::mock();
    $emailsMock->shouldReceive('send')->andReturn($fakeEmail);
    Resend::shouldReceive('emails')->andReturn($emailsMock);
}

// ──────────────────────────────────────────────────────────────────────────────
// resendInvoice — authentication
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 when unauthenticated on resend invoice', function () {
    // Auth middleware fires before model binding — any UUID works
    $this->postJson(route('api.v1.resend.invoice', ['invoice' => \Illuminate\Support\Str::uuid()]))
        ->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// resendInvoice — success path
// ──────────────────────────────────────────────────────────────────────────────

it('returns 200 with success message when invoice is sent', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForEmail();
    fakeResendSend();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertOk();

    expect($response->json('message'))->toBe('Invoice sent successfully');
});

it('creates an InvoiceEmailEvent with event_type sent after resend', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForEmail();
    fakeResendSend('resend-abc-999');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertOk();

    $event = InvoiceEmailEvent::where('invoice_id', $invoice->id)->first();
    expect($event)->not->toBeNull();
    expect($event->event_type)->toBe('sent');
    expect($event->resend_email_id)->toBe('resend-abc-999');
    expect($event->organization_id)->toBe($invoice->organization_id);
});

it('stores the recipient email address on the sent event', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForEmail(['email' => 'landlord@test.com']);
    fakeResendSend();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertOk();

    $event = InvoiceEmailEvent::where('invoice_id', $invoice->id)->first();
    expect($event->email)->toBe('landlord@test.com');
});

it('sets sent_at on the invoice after resend', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForEmail();
    fakeResendSend();

    expect($invoice->sent_at)->toBeNull();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertOk();

    expect($invoice->fresh()->sent_at)->not->toBeNull();
});

it('clears previous email events before creating a new sent event', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForEmail();

    // Seed two old events for this invoice
    InvoiceEmailEvent::factory()->count(2)->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'delivered',
        'resend_email_id' => 'old-resend-id',
        'email'           => 'old@example.com',
        'occurred_at'     => now()->subDay(),
    ]);

    fakeResendSend('new-resend-id');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertOk();

    // Only the single new 'sent' event should remain
    $events = InvoiceEmailEvent::where('invoice_id', $invoice->id)->get();
    expect($events)->toHaveCount(1);
    expect($events->first()->event_type)->toBe('sent');
    expect($events->first()->resend_email_id)->toBe('new-resend-id');
});

it('records a null resend_email_id when Resend returns no id', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForEmail();

    // Resend returns an object with no id (simulate API hiccup)
    $fakeEmail  = \Resend\Email::from([]);
    $emailsMock = Mockery::mock();
    $emailsMock->shouldReceive('send')->andReturn($fakeEmail);
    Resend::shouldReceive('emails')->andReturn($emailsMock);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertOk();

    $event = InvoiceEmailEvent::where('invoice_id', $invoice->id)->first();
    expect($event->resend_email_id)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// resendInvoice — error cases
// ──────────────────────────────────────────────────────────────────────────────

it('returns 500 when the owner has no email address', function () {
    // owners.email is NOT NULL — use empty string to simulate missing email
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForEmail(['email' => '']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertStatus(500);
});

it('does not create an email event when recipient has no email', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForEmail(['email' => '']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertStatus(500);

    expect(InvoiceEmailEvent::where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

it('returns 500 when the invoice has no billed-to recipient', function () {
    $user   = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $ct     = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    // billed_to_id points to a non-existent owner UUID → billedToOwner will be null
    $invoice = Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => (string) \Illuminate\Support\Str::uuid(),
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', $invoice))
        ->assertStatus(500);
});

it('returns 404 for a non-existent invoice', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.resend.invoice', \Illuminate\Support\Str::uuid()))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// Resend webhook — authentication & basic routing
// ──────────────────────────────────────────────────────────────────────────────

it('webhook route is public and does not require authentication', function () {
    $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'some-id'],
    ])->assertOk();
});

it('webhook returns a message when no email_id is in the payload', function () {
    $response = $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.delivered',
        'data' => [],
    ])->assertOk();

    expect($response->json('message'))->toContain('No email ID');
});

it('webhook returns a message for an unhandled event type', function () {
    $response = $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.bounced',
        'data' => ['email_id' => 'some-id'],
    ])->assertOk();

    expect($response->json('message'))->toContain('Unhandled event type');
});

it('webhook returns a message when no matching sent event is found', function () {
    $response = $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'unknown-resend-id-xyz'],
    ])->assertOk();

    expect($response->json('message'))->toContain('No matching sent event');
});

// ──────────────────────────────────────────────────────────────────────────────
// Resend webhook — email.delivered
// ──────────────────────────────────────────────────────────────────────────────

it('webhook creates a delivered event when email.delivered is received', function () {
    ['invoice' => $invoice] = makeInvoiceForEmail();

    $sentEvent = InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'sent',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-id-deliver-001',
        'occurred_at'     => now()->subMinutes(2),
    ]);

    $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'resend-id-deliver-001'],
    ])->assertOk();

    $deliveredEvent = InvoiceEmailEvent::where('invoice_id', $invoice->id)
        ->where('event_type', 'delivered')
        ->first();

    expect($deliveredEvent)->not->toBeNull();
    expect($deliveredEvent->resend_email_id)->toBe('resend-id-deliver-001');
    expect($deliveredEvent->organization_id)->toBe($invoice->organization_id);
    expect($deliveredEvent->email)->toBe('owner@example.com');
});

it('webhook returns processed message for a delivered event', function () {
    ['invoice' => $invoice] = makeInvoiceForEmail();

    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'sent',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-id-deliver-002',
        'occurred_at'     => now()->subMinutes(1),
    ]);

    $response = $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'resend-id-deliver-002'],
    ])->assertOk();

    expect($response->json('message'))->toBe('Webhook processed');
});

// ──────────────────────────────────────────────────────────────────────────────
// Resend webhook — email.opened
// ──────────────────────────────────────────────────────────────────────────────

it('webhook creates an opened event when email.opened is received', function () {
    ['invoice' => $invoice] = makeInvoiceForEmail();

    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'sent',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-id-open-001',
        'occurred_at'     => now()->subMinutes(3),
    ]);

    $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.opened',
        'data' => ['email_id' => 'resend-id-open-001', 'user_agent' => 'Mozilla/5.0'],
    ])->assertOk();

    $openedEvent = InvoiceEmailEvent::where('invoice_id', $invoice->id)
        ->where('event_type', 'opened')
        ->first();

    expect($openedEvent)->not->toBeNull();
    expect($openedEvent->resend_email_id)->toBe('resend-id-open-001');
});

it('webhook stores the full data payload as metadata on opened events', function () {
    ['invoice' => $invoice] = makeInvoiceForEmail();

    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'sent',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-id-meta-001',
        'occurred_at'     => now()->subMinutes(1),
    ]);

    $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.opened',
        'data' => [
            'email_id'   => 'resend-id-meta-001',
            'user_agent' => 'AppleMail/3.6',
            'ip'         => '1.2.3.4',
        ],
    ])->assertOk();

    $openedEvent = InvoiceEmailEvent::where('invoice_id', $invoice->id)
        ->where('event_type', 'opened')
        ->first();

    expect($openedEvent->metadata)->toMatchArray([
        'email_id'   => 'resend-id-meta-001',
        'user_agent' => 'AppleMail/3.6',
        'ip'         => '1.2.3.4',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Resend webhook — duplicate prevention
// ──────────────────────────────────────────────────────────────────────────────

it('webhook does not create a duplicate delivered event on repeated calls', function () {
    ['invoice' => $invoice] = makeInvoiceForEmail();

    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'sent',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-id-dup-001',
        'occurred_at'     => now()->subMinutes(2),
    ]);

    // Fire the same delivered webhook twice
    $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'resend-id-dup-001'],
    ])->assertOk();

    $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'resend-id-dup-001'],
    ])->assertOk();

    $count = InvoiceEmailEvent::where('invoice_id', $invoice->id)
        ->where('event_type', 'delivered')
        ->count();

    expect($count)->toBe(1);
});

it('webhook does not create a duplicate opened event on repeated calls', function () {
    ['invoice' => $invoice] = makeInvoiceForEmail();

    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'sent',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-id-dup-002',
        'occurred_at'     => now()->subMinutes(2),
    ]);

    // Fire opened three times (Resend may fire this multiple times)
    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('api.v1.webhooks.resend'), [
            'type' => 'email.opened',
            'data' => ['email_id' => 'resend-id-dup-002'],
        ])->assertOk();
    }

    $count = InvoiceEmailEvent::where('invoice_id', $invoice->id)
        ->where('event_type', 'opened')
        ->count();

    expect($count)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Resend webhook — multi-invoice isolation
// ──────────────────────────────────────────────────────────────────────────────

it('webhook delivered event is scoped to the correct invoice', function () {
    ['invoice' => $invoiceA] = makeInvoiceForEmail();
    ['invoice' => $invoiceB] = makeInvoiceForEmail();

    // Only invoiceA has a matching sent event
    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoiceA->id,
        'organization_id' => $invoiceA->organization_id,
        'event_type'      => 'sent',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-id-scope-001',
        'occurred_at'     => now()->subMinutes(1),
    ]);

    $this->postJson(route('api.v1.webhooks.resend'), [
        'type' => 'email.delivered',
        'data' => ['email_id' => 'resend-id-scope-001'],
    ])->assertOk();

    // Delivered event lands on invoiceA only
    expect(InvoiceEmailEvent::where('invoice_id', $invoiceA->id)->where('event_type', 'delivered')->exists())->toBeTrue();
    expect(InvoiceEmailEvent::where('invoice_id', $invoiceB->id)->where('event_type', 'delivered')->exists())->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// InvoiceEmailEvent tracking — emailEvents relationship
// ──────────────────────────────────────────────────────────────────────────────

it('invoice emailEvents relationship returns events in chronological order', function () {
    ['invoice' => $invoice] = makeInvoiceForEmail();

    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'sent',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-order-001',
        'occurred_at'     => now()->subMinutes(10),
    ]);
    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'delivered',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-order-001',
        'occurred_at'     => now()->subMinutes(5),
    ]);
    InvoiceEmailEvent::factory()->create([
        'invoice_id'      => $invoice->id,
        'organization_id' => $invoice->organization_id,
        'event_type'      => 'opened',
        'email'           => 'owner@example.com',
        'resend_email_id' => 'resend-order-001',
        'occurred_at'     => now()->subMinutes(1),
    ]);

    $events = $invoice->emailEvents()->get();
    expect($events)->toHaveCount(3);
    expect($events[0]->event_type)->toBe('sent');
    expect($events[1]->event_type)->toBe('delivered');
    expect($events[2]->event_type)->toBe('opened');
});
