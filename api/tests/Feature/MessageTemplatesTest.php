<?php

use App\Helpers\MessageTemplateDefaults;
use App\Models\MessageTemplate;

// =============================================================================
// Unauthenticated access
// =============================================================================

it('blocks unauthenticated access to message templates', function (): void {
    $this->getJson(route('api.v1.show.message.templates'))->assertUnauthorized();
});

// =============================================================================
// Index — lazy seeding of defaults
// =============================================================================

it('seeds all default message templates on first load', function (): void {
    $user = adminUser();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.message.templates'))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(count(MessageTemplateDefaults::all()));

    // 9 email + 3 SMS
    $types = collect($data)->groupBy('type')->map->count();
    expect($types['email'])->toBe(9);
    expect($types['sms'])->toBe(3);
});

it('is idempotent — does not duplicate templates on repeated loads', function (): void {
    $user = adminUser();

    $this->actingAs($user, 'api')->getJson(route('api.v1.show.message.templates'))->assertOk();
    $this->actingAs($user, 'api')->getJson(route('api.v1.show.message.templates'))->assertOk();

    expect(MessageTemplate::where('organization_id', $user->organization_id)->count())
        ->toBe(count(MessageTemplateDefaults::all()));
});

it('returns templates in canonical order', function (): void {
    $user = adminUser();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.message.templates'))
        ->assertOk()
        ->json('data');

    expect($data[0]['key'])->toBe('first_notice');
    expect($data[0]['name'])->toBe('1st Notice');
});

// =============================================================================
// Update
// =============================================================================

it('updates a template subject and body', function (): void {
    $user = adminUser();
    $this->actingAs($user, 'api')->getJson(route('api.v1.show.message.templates'));

    $template = MessageTemplate::where('organization_id', $user->organization_id)
        ->where('key', 'first_notice')->first();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.message.template', ['messageTemplate' => $template->id]), [
            'subject' => 'CUSTOM SUBJECT',
            'body'    => '<p>Custom body</p>',
        ])
        ->assertOk()
        ->assertJsonPath('data.subject', 'CUSTOM SUBJECT');

    expect($template->fresh()->body)->toBe('<p>Custom body</p>');
});

it('prevents updating a template belonging to another organization', function (): void {
    $user  = adminUser();
    $other = otherOrganizationUser();
    $this->actingAs($other, 'api')->getJson(route('api.v1.show.message.templates'));

    $foreign = MessageTemplate::where('organization_id', $other->organization_id)->first();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.message.template', ['messageTemplate' => $foreign->id]), [
            'subject' => 'HIJACK',
        ])
        ->assertForbidden();
});

// =============================================================================
// Reset
// =============================================================================

it('resets a template to its default content', function (): void {
    $user = adminUser();
    $this->actingAs($user, 'api')->getJson(route('api.v1.show.message.templates'));

    $template = MessageTemplate::where('organization_id', $user->organization_id)
        ->where('key', 'first_notice')->first();
    $template->update(['subject' => 'CHANGED', 'body' => 'changed']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.reset.message.template', ['messageTemplate' => $template->id]))
        ->assertOk()
        ->assertJsonPath('data.subject', 'ARREARS NOTICE');

    $default = MessageTemplateDefaults::find('first_notice');
    expect($template->fresh()->body)->toBe($default['body']);
});
