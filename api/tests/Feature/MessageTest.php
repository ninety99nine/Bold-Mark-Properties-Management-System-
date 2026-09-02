<?php

use Illuminate\Support\Facades\Mail;

// ──────────────────────────────────────────────────────────────────────────────
// send — POST /messages/send
// ──────────────────────────────────────────────────────────────────────────────

it('send message returns 401 without auth', function () {
    $this->postJson(route('api.v1.messages.send'), [])
        ->assertUnauthorized();
});

it('send message returns 200 with success message on valid payload', function () {
    Mail::fake();
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.messages.send'), [
            'recipient_name'  => 'John Owner',
            'recipient_email' => 'john@example.com',
            'subject'         => 'Your invoice is ready',
            'body'            => 'Please find your invoice attached.',
        ])
        ->assertOk()
        ->assertJson(['message' => 'Email sent successfully.']);
});

it('send message requires recipient_name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.messages.send'), [
            'recipient_email' => 'john@example.com',
            'subject'         => 'Subject',
            'body'            => 'Body',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipient_name');
});

it('send message requires recipient_email', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.messages.send'), [
            'recipient_name' => 'John',
            'subject'        => 'Subject',
            'body'           => 'Body',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipient_email');
});

it('send message rejects an invalid recipient_email', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.messages.send'), [
            'recipient_name'  => 'John',
            'recipient_email' => 'not-an-email',
            'subject'         => 'Subject',
            'body'            => 'Body',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipient_email');
});

it('send message requires subject', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.messages.send'), [
            'recipient_name'  => 'John',
            'recipient_email' => 'john@example.com',
            'body'            => 'Body',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subject');
});

it('send message requires body', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.messages.send'), [
            'recipient_name'  => 'John',
            'recipient_email' => 'john@example.com',
            'subject'         => 'Subject',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

it('send message returns 500 when the mailer throws', function () {
    Mail::shouldReceive('mailer')->andThrow(new \Exception('SMTP connection failed'));
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.messages.send'), [
            'recipient_name'  => 'John',
            'recipient_email' => 'john@example.com',
            'subject'         => 'Subject',
            'body'            => 'Body',
        ])
        ->assertStatus(500)
        ->assertJson(['message' => 'Failed to send email. Please try again.']);
});
