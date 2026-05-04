<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

// ──────────────────────────────────────────────────────────────────────────────
// Setup helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Passport's createToken() requires a Personal Access Client to exist.
 * The DatabaseSeeder creates one, but RefreshDatabase wipes it between tests.
 * We re-create it before every test so token issuance works end-to-end.
 */
beforeEach(function () {
    if (! Client::where('personal_access_client', true)->exists()) {
        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }
});

/**
 * Create a tenant + user with a known password.
 */
function userWithPassword(string $email = 'user@boldmark.test', string $password = 'password123'): User
{
    return User::factory()->create([
        'organization_id' => createTenant()->id,
        'email'     => $email,
        'password'  => Hash::make($password),
    ]);
}

/**
 * Log in via the real endpoint and return the access token string.
 * Use this anywhere a test needs an authenticated request that exercises
 * the full Passport pipeline (rather than the actingAs() shortcut).
 */
function loginAndGetToken(string $email, string $password): string
{
    return test()->postJson(route('api.v1.auth.login'), [
        'email'    => $email,
        'password' => $password,
    ])->json('data.token');
}

/**
 * Passport's TokenGuard caches the resolved user on its singleton instance.
 * In production each request is a fresh PHP-FPM process, so this is fine.
 * In tests, multiple sequential requests reuse the same kernel — so after
 * we revoke a token (or in any flow where token validity changes), the
 * next ->getJson() / ->postJson() call would still see the cached user.
 *
 * Call this between such requests to force the next call to re-validate
 * the bearer token through the OAuth ResourceServer.
 */
function forgetAuth(): void
{
    \Illuminate\Support\Facades\Auth::forgetGuards();
}

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/auth/login                                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Validation rules
// ──────────────────────────────────────────────────────────────────────────────

it('returns 422 when login email is missing', function () {
    $this->postJson(route('api.v1.auth.login'), ['password' => 'password123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when login email is empty string', function () {
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => '',
        'password' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when login email is null', function () {
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => null,
        'password' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when login email is not a valid email format', function (string $bad) {
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => $bad,
        'password' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
})->with([
    'plain text'           => ['notanemail'],
    'missing tld'          => ['user@host'],
    'missing local'        => ['@host.com'],
    'spaces inside'        => ['us er@host.com'],
    'double-at'            => ['user@@host.com'],
    'sql-ish injection'    => ["' OR 1=1 --"],
    'angle-bracket xss'    => ['<script>@x.com'],
]);

it('returns 422 when login email is not a string (array)', function () {
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => ['user@boldmark.test'],
        'password' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when login password is missing', function () {
    $this->postJson(route('api.v1.auth.login'), ['email' => 'user@boldmark.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when login password is empty string', function () {
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'user@boldmark.test',
        'password' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when login password is null', function () {
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'user@boldmark.test',
        'password' => null,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when both login fields are missing', function () {
    $this->postJson(route('api.v1.auth.login'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Credentials & success path
// ──────────────────────────────────────────────────────────────────────────────

it('returns a token and user on successful login', function () {
    userWithPassword('admin@boldmark.test', 'password123');

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'admin@boldmark.test',
        'password' => 'password123',
    ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email', 'organization_id'],
            ],
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
    expect($response->json('data.user.email'))->toBe('admin@boldmark.test');
});

it('does not include password or remember_token in login response', function () {
    userWithPassword('admin@boldmark.test', 'password123');

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'admin@boldmark.test',
        'password' => 'password123',
    ])->assertOk();

    expect($response->json('data.user'))
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

it('rejects login with wrong password', function () {
    userWithPassword('real@boldmark.test', 'correct-password');

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'real@boldmark.test',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects login for an unknown email', function () {
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'ghost@boldmark.test',
        'password' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns the same generic error for unknown email and wrong password (no enumeration)', function () {
    userWithPassword('real@boldmark.test', 'correct-password');

    $unknown = $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'ghost@boldmark.test',
        'password' => 'whatever',
    ])->assertUnprocessable();

    $wrongPass = $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'real@boldmark.test',
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    expect($unknown->json('errors'))->toEqual($wrongPass->json('errors'));
});

it('does not allow login via SQL injection in the email field', function () {
    userWithPassword('victim@boldmark.test', 'password123');

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => "victim@boldmark.test' OR '1'='1",
        'password' => 'password123',
    ])->assertUnprocessable();

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => "' OR 1=1 --",
        'password' => 'anything',
    ])->assertUnprocessable();
});

it('hashes the password on creation rather than storing it plain', function () {
    $user = userWithPassword('hash-check@boldmark.test', 'super-secret');

    expect($user->fresh()->password)
        ->not->toBe('super-secret')
        ->toStartWith('$2y$'); // bcrypt prefix
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/auth/me                                                          ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on GET /auth/me when no token is provided', function () {
    $this->getJson(route('api.v1.auth.me'))->assertUnauthorized();
});

it('returns 401 on GET /auth/me with a malformed Bearer token', function () {
    $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized();
});

it('returns 401 on GET /auth/me with an empty Bearer token', function () {
    $this->withHeader('Authorization', 'Bearer ')
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized();
});

it('returns the authenticated user (with roles & permissions) on GET /auth/me', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'email', 'name', 'organization_id', 'roles', 'permissions'],
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);

    expect($response->json('data.roles'))->toBeArray();
    expect($response->json('data.permissions'))->toBeArray();
});

it('does not expose password or remember_token on GET /auth/me', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.auth.me'))
        ->assertOk();

    expect($response->json('data'))
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

it('returns the right user when issued a real Passport token', function () {
    userWithPassword('me-token@boldmark.test', 'password123');
    $token = loginAndGetToken('me-token@boldmark.test', 'password123');

    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'me-token@boldmark.test');
});

it('does not leak another user via a token belonging to user A', function () {
    userWithPassword('userA@boldmark.test', 'password123');
    userWithPassword('userB@boldmark.test', 'password123');

    $tokenA = loginAndGetToken('userA@boldmark.test', 'password123');

    $this->withHeader('Authorization', "Bearer $tokenA")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'userA@boldmark.test');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/auth/logout                                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on POST /auth/logout when unauthenticated', function () {
    $this->postJson(route('api.v1.auth.logout'))->assertUnauthorized();
});

it('logs out and revokes the access token', function () {
    userWithPassword('logout@boldmark.test', 'password123');
    $token = loginAndGetToken('logout@boldmark.test', 'password123');

    // First logout call succeeds and revokes the token.
    $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.logout'))
        ->assertOk()
        ->assertJson(['message' => 'Logged out successfully.']);

    // The persisted token row is flagged revoked.
    expect(DB::table('oauth_access_tokens')->where('revoked', true)->count())->toBe(1);

    // Same token should now be rejected on any auth:api route.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized();

    // And calling logout again should also fail.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.logout'))
        ->assertUnauthorized();
});

it('only revokes the token used for the request, not all of the user’s tokens', function () {
    userWithPassword('multi-token@boldmark.test', 'password123');
    $token1 = loginAndGetToken('multi-token@boldmark.test', 'password123');
    $token2 = loginAndGetToken('multi-token@boldmark.test', 'password123');

    // Log out using token1.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token1")
        ->postJson(route('api.v1.auth.logout'))
        ->assertOk();

    // token1 must be revoked.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token1")
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized();

    // token2 must still work — logout is per-token, not per-user.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token2")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/auth/forgot-password                                            ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Validation rules
// ──────────────────────────────────────────────────────────────────────────────

it('returns 422 when forgot-password email is missing', function () {
    $this->postJson(route('api.v1.auth.forgot-password'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when forgot-password email is empty', function () {
    $this->postJson(route('api.v1.auth.forgot-password'), ['email' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when forgot-password email is not a valid email', function (string $bad) {
    $this->postJson(route('api.v1.auth.forgot-password'), ['email' => $bad])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
})->with([
    'plain text'    => ['notanemail'],
    'missing local' => ['@host.com'],
    'whitespace'    => ['has spaces@host.com'],
    'sql-ish'       => ["' OR 1=1 --"],
]);

// ──────────────────────────────────────────────────────────────────────────────
// Behavior
// ──────────────────────────────────────────────────────────────────────────────

it('sends a password-reset notification with a token to an existing user', function () {
    Notification::fake();

    $user = userWithPassword('forgot@boldmark.test', 'password123');

    $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'forgot@boldmark.test',
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) {
        return is_string($n->token) && strlen($n->token) >= 40;
    });
});

it('persists a password-reset token row for the user', function () {
    userWithPassword('forgot@boldmark.test', 'password123');

    $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'forgot@boldmark.test',
    ])->assertOk();

    $row = DB::table('password_reset_tokens')
        ->where('email', 'forgot@boldmark.test')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->token)->toBeString()->not->toBeEmpty();
    // The stored token is hashed — should never equal a known plain value.
    expect(strlen($row->token))->toBeGreaterThan(20);
});

it('does not send a notification for an unknown email', function () {
    Notification::fake();

    $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'nobody@boldmark.test',
    ])->assertOk();

    Notification::assertNothingSent();
});

it('throttles repeated forgot-password requests for the same user', function () {
    Notification::fake();
    userWithPassword('throttle@boldmark.test', 'password123');

    $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'throttle@boldmark.test',
    ])->assertOk();

    // Second immediate call should hit the broker's throttle (config: 60s).
    $second = $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'throttle@boldmark.test',
    ])->assertOk();

    expect($second->json('message'))->toContain('wait before retrying');
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ POST /v1/auth/reset-password                                             ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Validation rules
// ──────────────────────────────────────────────────────────────────────────────

it('returns 422 when reset-password is called with no payload', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'email', 'password']);
});

it('returns 422 when reset-password token is missing', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [
        'email'                 => 'user@boldmark.test',
        'password'              => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

it('returns 422 when reset-password email is missing', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => 'some-token',
        'password'              => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when reset-password email is not a valid email', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => 'some-token',
        'email'                 => 'not-an-email',
        'password'              => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when reset-password password is missing', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token' => 'some-token',
        'email' => 'user@boldmark.test',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when reset-password password is shorter than 8 characters', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => 'some-token',
        'email'                 => 'user@boldmark.test',
        'password'              => 'short',
        'password_confirmation' => 'short',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when reset-password password_confirmation does not match', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => 'some-token',
        'email'                 => 'user@boldmark.test',
        'password'              => 'new-password-1',
        'password_confirmation' => 'different-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 when reset-password password_confirmation is missing', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'    => 'some-token',
        'email'    => 'user@boldmark.test',
        'password' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Token-handling security
// ──────────────────────────────────────────────────────────────────────────────

it('rejects reset-password when the token is wrong', function () {
    userWithPassword('user@boldmark.test', 'old-password');
    Password::broker('users')->createToken(User::firstWhere('email', 'user@boldmark.test'));

    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => 'totally-invalid-token',
        'email'                 => 'user@boldmark.test',
        'password'              => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    // Old password must still work — reset failed.
    expect(Hash::check('old-password', User::firstWhere('email', 'user@boldmark.test')->password))->toBeTrue();
});

it('rejects reset-password when the email does not match the token owner', function () {
    $userA = userWithPassword('a@boldmark.test', 'old-password');
    userWithPassword('b@boldmark.test', 'old-password');

    $tokenForA = Password::broker('users')->createToken($userA);

    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $tokenForA,
        'email'                 => 'b@boldmark.test', // wrong owner
        'password'              => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    // Both users still have their old password.
    expect(Hash::check('old-password', User::firstWhere('email', 'a@boldmark.test')->password))->toBeTrue();
    expect(Hash::check('old-password', User::firstWhere('email', 'b@boldmark.test')->password))->toBeTrue();
});

it('rejects reset-password when no token has been issued for the email', function () {
    userWithPassword('no-token@boldmark.test', 'old-password');

    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => 'any-token',
        'email'                 => 'no-token@boldmark.test',
        'password'              => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects reset-password when the token has expired (users broker = 1440min / 24h)', function () {
    $user  = userWithPassword('expire@boldmark.test', 'old-password');
    $token = Password::broker('users')->createToken($user);

    // Backdate the token to be older than 24h so it's considered expired.
    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => Carbon::now()->subHours(25)]);

    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('rejects reset-password when the email is unknown to the broker', function () {
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => 'some-token',
        'email'                 => 'ghost@boldmark.test',
        'password'              => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Successful reset
// ──────────────────────────────────────────────────────────────────────────────

it('resets the password with a valid token and email', function () {
    $user  = userWithPassword('ok@boldmark.test', 'old-password');
    $token = Password::broker('users')->createToken($user);

    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $token,
        'email'                 => 'ok@boldmark.test',
        'password'              => 'new-strong-pw-1',
        'password_confirmation' => 'new-strong-pw-1',
    ])->assertOk();

    $fresh = $user->fresh();
    expect(Hash::check('new-strong-pw-1', $fresh->password))->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeFalse();
});

it('consumes the reset token (cannot be reused after success)', function () {
    $user  = userWithPassword('once@boldmark.test', 'old-password');
    $token = Password::broker('users')->createToken($user);

    // First call succeeds.
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'new-strong-pw-1',
        'password_confirmation' => 'new-strong-pw-1',
    ])->assertOk();

    // Token row should be gone.
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();

    // Second call with same token must fail.
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'another-pw-2',
        'password_confirmation' => 'another-pw-2',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('revokes all of the user’s API tokens after a successful password reset', function () {
    $user = userWithPassword('revoke@boldmark.test', 'old-password');

    // Issue two real tokens.
    $token1 = loginAndGetToken('revoke@boldmark.test', 'old-password');
    $token2 = loginAndGetToken('revoke@boldmark.test', 'old-password');

    expect(DB::table('oauth_access_tokens')->where('user_id', $user->id)->count())->toBe(2);

    // Reset the password.
    $resetToken = Password::broker('users')->createToken($user);
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $resetToken,
        'email'                 => $user->email,
        'password'              => 'new-strong-pw-1',
        'password_confirmation' => 'new-strong-pw-1',
    ])->assertOk();

    // The controller calls `tokens()->delete()` which detaches Passport tokens for this user.
    expect(DB::table('oauth_access_tokens')->where('user_id', $user->id)->count())->toBe(0);

    // Both previously-issued tokens must be unusable now.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token1")
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized();

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token2")
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized();
});

it('lets the user log in with the new password and rejects the old one after reset', function () {
    $user  = userWithPassword('rotate@boldmark.test', 'old-password');
    $token = Password::broker('users')->createToken($user);

    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'new-strong-pw-1',
        'password_confirmation' => 'new-strong-pw-1',
    ])->assertOk();

    // Old password no longer works.
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => $user->email,
        'password' => 'old-password',
    ])->assertUnprocessable();

    // New password works.
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => $user->email,
        'password' => 'new-strong-pw-1',
    ])->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// Broker selection (users vs invitations)
// ──────────────────────────────────────────────────────────────────────────────
//
// The controller whitelists ['users', 'invitations'] and falls back to 'users'
// for any other value. Both brokers share the same `password_reset_tokens`
// table, so token *storage* is shared; the broker config differs in `expire`.

it('falls back to the users broker when an invalid broker name is supplied', function () {
    $user  = userWithPassword('fallback@boldmark.test', 'old-password');
    $token = Password::broker('users')->createToken($user);

    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'new-strong-pw-1',
        'password_confirmation' => 'new-strong-pw-1',
        'broker'                => 'something-malicious',
    ])->assertOk();

    expect(Hash::check('new-strong-pw-1', $user->fresh()->password))->toBeTrue();
});

it('falls back to the users broker when broker is null', function () {
    $user  = userWithPassword('null-broker@boldmark.test', 'old-password');
    $token = Password::broker('users')->createToken($user);

    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'new-strong-pw-1',
        'password_confirmation' => 'new-strong-pw-1',
        'broker'                => null,
    ])->assertOk();
});

it('rejects unknown broker values in the same way as the default broker (no SQL/path injection)', function () {
    $user = userWithPassword('safe@boldmark.test', 'old-password');
    Password::broker('users')->createToken($user);

    // Try to abuse the broker field — must not crash, must just fall back.
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => 'wrong-token',
        'email'                 => $user->email,
        'password'              => 'new-strong-pw-1',
        'password_confirmation' => 'new-strong-pw-1',
        'broker'                => "'; DROP TABLE users; --",
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    // Sanity — table still there.
    expect(DB::table('users')->count())->toBeGreaterThan(0);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ End-to-end: full forgot → reset flow                                     ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('runs the full forgot-password → reset-password → re-login flow', function () {
    Notification::fake();

    $user = userWithPassword('flow@boldmark.test', 'old-password');

    // 1. Request a reset email.
    $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => $user->email,
    ])->assertOk();

    // 2. Capture the token from the queued notification.
    $captured = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$captured) {
        $captured = $n->token;
        return true;
    });

    expect($captured)->toBeString()->not->toBeEmpty();

    // 3. Submit the reset.
    $this->postJson(route('api.v1.auth.reset-password'), [
        'token'                 => $captured,
        'email'                 => $user->email,
        'password'              => 'brand-new-pw-1',
        'password_confirmation' => 'brand-new-pw-1',
    ])->assertOk();

    // 4. Old password fails, new password works.
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => $user->email,
        'password' => 'old-password',
    ])->assertUnprocessable();

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => $user->email,
        'password' => 'brand-new-pw-1',
    ])->assertOk();
});
