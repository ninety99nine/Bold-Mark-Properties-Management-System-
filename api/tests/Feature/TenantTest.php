<?php

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on tenant routes when unauthenticated', function () {
    $this->getJson(route('api.v1.show.tenant'))->assertUnauthorized();
    $this->putJson(route('api.v1.update.tenant'))->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /tenant (show current tenant)
// ──────────────────────────────────────────────────────────────────────────────

it('returns the current tenant for the authenticated user', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.tenant'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->tenant_id);
});

it('each user only sees their own tenant', function () {
    $userA = adminUser();
    $userB = adminUser(); // different tenant from adminUser() calling createTenant() each time

    $responseA = $this->actingAs($userA, 'api')
        ->getJson(route('api.v1.show.tenant'))
        ->assertOk();

    $responseB = $this->actingAs($userB, 'api')
        ->getJson(route('api.v1.show.tenant'))
        ->assertOk();

    expect($responseA->json('data.id'))->not->toBe($responseB->json('data.id'));
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /tenant (update current tenant)
// ──────────────────────────────────────────────────────────────────────────────

it('updates tenant company settings', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'company_name'    => 'Bold Mark Properties Updated',
            'company_slogan'  => 'Moving People Forward',
            'contact_email'   => 'info@boldmark.co.za',
            'primary_color'   => '#1F3A5C',
            'secondary_color' => '#D89B4B',
        ])
        ->assertOk()
        ->assertJsonPath('data.company_name', 'Bold Mark Properties Updated');

    $this->assertDatabaseHas('tenants', [
        'id'           => $user->tenant_id,
        'company_name' => 'Bold Mark Properties Updated',
    ]);
});

it('returns 422 when tenant contact_email is invalid', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'contact_email' => 'not-a-valid-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_email']);
});

it('returns 422 when tenant company_name exceeds 255 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'company_name' => str_repeat('x', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name']);
});

it('returns 422 when tenant contact_phone exceeds 30 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'contact_phone' => str_repeat('1', 31),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_phone']);
});

it('returns 422 when tenant address exceeds 500 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'address' => str_repeat('x', 501),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address']);
});

it('returns 422 when tenant country is not exactly 2 characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'country' => 'ZAF',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('returns 422 when tenant primary_color is missing the hash prefix', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'primary_color' => '1F3A5C',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_color']);
});

it('returns 422 when tenant primary_color is an invalid hex format', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'primary_color' => '#GGHHII',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_color']);
});

it('returns 422 when tenant secondary_color is missing the hash prefix', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'secondary_color' => 'D89B4B',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secondary_color']);
});

it('returns 422 when tenant secondary_color is an invalid hex format', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'secondary_color' => '#XYZXYZ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secondary_color']);
});

it('accepts valid 3-character hex for primary_color', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'primary_color' => '#FFF',
        ])
        ->assertOk();
});

it('accepts valid 6-character hex for secondary_color', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'secondary_color' => '#D89B4B',
        ])
        ->assertOk();
});

it('accepts exactly 2-character country code', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.tenant'), [
            'country' => 'BW',
        ])
        ->assertOk();
});
