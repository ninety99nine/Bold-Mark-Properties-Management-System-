<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ──────────────────────────────────────────────────────────────────────────────
// POST /auth/login
// ──────────────────────────────────────────────────────────────────────────────

it('returns a token on successful login', function () {
    $tenant = createTenant();
    $user   = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email'     => 'admin@boldmark.test',
        'password'  => Hash::make('password123'),
    ]);

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'admin@boldmark.test',
        'password' => 'password123',
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user']]);
});

it('returns 422 when login email is missing', function () {
    $this->postJson(route('api.v1.auth.login'), ['password' => 'password123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when login password is missing', function () {
    $this->postJson(route('api.v1.auth.login'), ['email' => 'test@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 401 on invalid credentials', function () {
    $tenant = createTenant();
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email'     => 'real@boldmark.test',
        'password'  => Hash::make('correct-password'),
    ]);

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'real@boldmark.test',
        'password' => 'wrong-password',
    ])->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /auth/me
// ──────────────────────────────────────────────────────────────────────────────

it('returns the authenticated user on GET /auth/me', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('returns 401 on GET /auth/me when unauthenticated', function () {
    $this->getJson(route('api.v1.auth.me'))->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /auth/logout
// ──────────────────────────────────────────────────────────────────────────────

it('logs out an authenticated user', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.auth.logout'))
        ->assertOk();
});

it('returns 401 on POST /auth/logout when unauthenticated', function () {
    $this->postJson(route('api.v1.auth.logout'))->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /auth/forgot-password
// ──────────────────────────────────────────────────────────────────────────────

it('returns 422 when forgot-password email is missing', function () {
    $this->postJson(route('api.v1.auth.forgot-password'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('accepts forgot-password request for existing email', function () {
    $tenant = createTenant();
    $user   = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email'     => 'forgot@boldmark.test',
    ]);

    // Response should be 200 regardless of whether email exists (avoids user enumeration)
    $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'forgot@boldmark.test',
    ])->assertOk();
});
