<?php

use App\Enums\LoginFailureReason;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Models\UserSession;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use PragmaRX\Google2FA\Google2FA;

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

/**
 * Enable confirmed TOTP 2FA for a user and return the plaintext secret so the
 * test can generate valid codes with Google2FA::getCurrentOtp().
 */
function enableTwoFactorFor(User $user): string
{
    $secret = (new Google2FA())->generateSecretKey();

    $user->forceFill([
        'two_factor_secret'       => Crypt::encryptString($secret),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

/**
 * Generate the current valid 6-digit TOTP code for a secret.
 */
function totp(string $secret): string
{
    return (new Google2FA())->getCurrentOtp($secret);
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

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Login audit logging                                                      ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('creates a login log entry with correct fields on successful login', function () {
    $user = userWithPassword('log@boldmark.test', 'password123');

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'log@boldmark.test',
        'password' => 'password123',
    ])->assertOk();

    expect(UserLoginLog::count())->toBe(1);

    $log = UserLoginLog::first();
    expect($log->user_id)->toBe($user->id);
    expect($log->email)->toBe('log@boldmark.test');
    expect($log->login_successful)->toBeTrue();
    expect($log->failure_reason)->toBeNull();
});

it('updates last_login_at on successful login', function () {
    $user = userWithPassword('lastseen@boldmark.test', 'password123');

    expect($user->last_login_at)->toBeNull();

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'lastseen@boldmark.test',
        'password' => 'password123',
    ])->assertOk();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('logs wrong_password failure when the password is incorrect', function () {
    $user = userWithPassword('passcheck@boldmark.test', 'correct-password');

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'passcheck@boldmark.test',
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    $log = UserLoginLog::first();
    expect($log->user_id)->toBe($user->id);
    expect($log->login_successful)->toBeFalse();
    expect($log->failure_reason)->toBe(LoginFailureReason::WRONG_PASSWORD);
});

it('logs user_not_found with null user_id when the email does not exist', function () {
    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'nobody@boldmark.test',
        'password' => 'password123',
    ])->assertUnprocessable();

    $log = UserLoginLog::first();
    expect($log->user_id)->toBeNull();
    expect($log->email)->toBe('nobody@boldmark.test');
    expect($log->login_successful)->toBeFalse();
    expect($log->failure_reason)->toBe(LoginFailureReason::USER_NOT_FOUND);
});

it('blocks login and logs account_inactive when user status is inactive', function () {
    $user = userWithPassword('inactive@boldmark.test', 'password123');
    $user->update(['status' => UserStatus::INACTIVE]);

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'inactive@boldmark.test',
        'password' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $log = UserLoginLog::first();
    expect($log->user_id)->toBe($user->id);
    expect($log->login_successful)->toBeFalse();
    expect($log->failure_reason)->toBe(LoginFailureReason::ACCOUNT_INACTIVE);
});

it('blocks login and logs account_invited when user status is invited', function () {
    $user = userWithPassword('invited@boldmark.test', 'password123');
    $user->update(['status' => UserStatus::INVITED]);

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'invited@boldmark.test',
        'password' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $log = UserLoginLog::first();
    expect($log->user_id)->toBe($user->id);
    expect($log->login_successful)->toBeFalse();
    expect($log->failure_reason)->toBe(LoginFailureReason::ACCOUNT_INVITED);
});

it('captures ip address and user agent in the log entry', function () {
    userWithPassword('iptest@boldmark.test', 'password123');

    $this->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
        ->postJson(route('api.v1.auth.login'), [
            'email'    => 'iptest@boldmark.test',
            'password' => 'password123',
        ])->assertOk();

    $log = UserLoginLog::first();
    expect($log->ip_address)->toBeString();
    expect($log->user_agent)->toBe('TestBrowser/1.0');
});

it('logs every attempt — failures and success are all recorded', function () {
    userWithPassword('multi@boldmark.test', 'password123');

    $this->postJson(route('api.v1.auth.login'), ['email' => 'ghost@boldmark.test', 'password' => 'x'])->assertUnprocessable();
    $this->postJson(route('api.v1.auth.login'), ['email' => 'multi@boldmark.test', 'password' => 'wrong'])->assertUnprocessable();
    $this->postJson(route('api.v1.auth.login'), ['email' => 'multi@boldmark.test', 'password' => 'password123'])->assertOk();

    expect(UserLoginLog::count())->toBe(3);
    expect(UserLoginLog::where('login_successful', true)->count())->toBe(1);
    expect(UserLoginLog::where('login_successful', false)->count())->toBe(2);
});

it('prunes old records per user, keeping only the configured limit', function () {
    config()->set('auth.login_log_per_user_limit', 3);

    $user = userWithPassword('prune@boldmark.test', 'password123');

    for ($i = 0; $i < 3; $i++) {
        UserLoginLog::create([
            'user_id'          => $user->id,
            'email'            => $user->email,
            'ip_address'       => '127.0.0.1',
            'user_agent'       => 'test',
            'login_successful' => true,
            'failure_reason'   => null,
        ]);
    }

    expect(UserLoginLog::where('user_id', $user->id)->count())->toBe(3);

    // 4th insert triggers pruning — still only 3 remain
    UserLoginLog::create([
        'user_id'          => $user->id,
        'email'            => $user->email,
        'ip_address'       => '127.0.0.1',
        'user_agent'       => 'test',
        'login_successful' => true,
        'failure_reason'   => null,
    ]);

    expect(UserLoginLog::where('user_id', $user->id)->count())->toBe(3);
});

it('prunes anonymous login log records beyond the anonymous limit', function () {
    config()->set('auth.login_log_anonymous_limit', 3);

    for ($i = 0; $i < 3; $i++) {
        UserLoginLog::create([
            'user_id'          => null,
            'email'            => "ghost{$i}@boldmark.test",
            'ip_address'       => '127.0.0.1',
            'user_agent'       => 'test',
            'login_successful' => false,
            'failure_reason'   => LoginFailureReason::USER_NOT_FOUND,
        ]);
    }

    expect(UserLoginLog::whereNull('user_id')->count())->toBe(3);

    // 4th insert should prune back to 3
    UserLoginLog::create([
        'user_id'          => null,
        'email'            => 'ghost4@boldmark.test',
        'ip_address'       => '127.0.0.1',
        'user_agent'       => 'test',
        'login_successful' => false,
        'failure_reason'   => LoginFailureReason::USER_NOT_FOUND,
    ]);

    expect(UserLoginLog::whereNull('user_id')->count())->toBe(3);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ BM-008 — Multi-Factor Authentication (TOTP 2FA)                          ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Setup
// ──────────────────────────────────────────────────────────────────────────────

it('requires authentication to start 2FA setup', function () {
    $this->postJson(route('api.v1.auth.2fa.setup'))->assertUnauthorized();
});

it('returns a secret and QR URI when starting 2FA setup', function () {
    userWithPassword('2fa-setup@boldmark.test', 'password123');
    $token = loginAndGetToken('2fa-setup@boldmark.test', 'password123');

    $res = $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.2fa.setup'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['qr_uri', 'secret', 'enabled']]);

    expect($res->json('data.enabled'))->toBeFalse();
    expect($res->json('data.secret'))->toBeString()->not->toBeEmpty();
    expect($res->json('data.qr_uri'))->toContain('otpauth://');
});

// ──────────────────────────────────────────────────────────────────────────────
// Confirm
// ──────────────────────────────────────────────────────────────────────────────

it('enables 2FA when confirming setup with a valid code', function () {
    $user  = userWithPassword('2fa-confirm@boldmark.test', 'password123');
    $token = loginAndGetToken('2fa-confirm@boldmark.test', 'password123');

    $secret = $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.2fa.setup'))
        ->json('data.secret');

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.2fa.confirm'), ['code' => totp($secret)])
        ->assertOk()
        ->assertJson(['message' => 'Two-factor authentication enabled.']);

    $fresh = $user->fresh();
    expect($fresh->hasTwoFactorEnabled())->toBeTrue();
    expect($fresh->two_factor_secret)->not->toBe($secret); // stored encrypted
});

it('rejects 2FA confirmation with an invalid code', function () {
    userWithPassword('2fa-bad@boldmark.test', 'password123');
    $token = loginAndGetToken('2fa-bad@boldmark.test', 'password123');

    $secret = $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.2fa.setup'))
        ->json('data.secret');

    $valid = totp($secret);
    $wrong = $valid === '000000' ? '111111' : '000000';

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.2fa.confirm'), ['code' => $wrong])
        ->assertStatus(422)
        ->assertJson(['message' => 'Invalid code. Please try again.']);
});

it('rejects 2FA confirmation when the setup session has expired', function () {
    userWithPassword('2fa-expired@boldmark.test', 'password123');
    $token = loginAndGetToken('2fa-expired@boldmark.test', 'password123');

    // No setup call → no pending secret cached.
    $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.2fa.confirm'), ['code' => '123456'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Setup session expired. Please start again.']);
});

it('validates the 2FA confirmation code format', function (array $payload) {
    userWithPassword('2fa-validate@boldmark.test', 'password123');
    $token = loginAndGetToken('2fa-validate@boldmark.test', 'password123');

    $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.2fa.confirm'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
})->with([
    'missing'      => [[]],
    'not digits'   => [['code' => 'abcdef']],
    'too short'    => [['code' => '123']],
    'too long'     => [['code' => '1234567']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// Disable
// ──────────────────────────────────────────────────────────────────────────────

it('disables 2FA with a valid code', function () {
    $user   = userWithPassword('2fa-disable@boldmark.test', 'password123');
    $secret = enableTwoFactorFor($user);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.auth.2fa.disable'), ['code' => totp($secret)])
        ->assertOk()
        ->assertJson(['message' => 'Two-factor authentication disabled.']);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('rejects disabling 2FA with an invalid code', function () {
    $user   = userWithPassword('2fa-disable-bad@boldmark.test', 'password123');
    $secret = enableTwoFactorFor($user);

    $valid = totp($secret);
    $wrong = $valid === '000000' ? '111111' : '000000';

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.auth.2fa.disable'), ['code' => $wrong])
        ->assertStatus(422);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('returns 422 when disabling 2FA that is not enabled', function () {
    $user = userWithPassword('2fa-not-on@boldmark.test', 'password123');

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.auth.2fa.disable'), ['code' => '123456'])
        ->assertStatus(422)
        ->assertJson(['message' => '2FA is not enabled.']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Login challenge flow
// ──────────────────────────────────────────────────────────────────────────────

it('returns a 2FA challenge instead of a token when 2FA is enabled', function () {
    $user = userWithPassword('2fa-login@boldmark.test', 'password123');
    enableTwoFactorFor($user);

    $res = $this->postJson(route('api.v1.auth.login'), [
        'email'    => '2fa-login@boldmark.test',
        'password' => 'password123',
    ])->assertOk();

    expect($res->json('data.two_factor_required'))->toBeTrue();
    expect($res->json('data.challenge'))->toBeString()->not->toBeEmpty();
    expect($res->json('data.token'))->toBeNull();

    // No token or session is issued until the challenge is passed.
    expect(DB::table('oauth_access_tokens')->count())->toBe(0);
    expect(UserSession::count())->toBe(0);
});

it('issues a token after a valid 2FA challenge and grants access', function () {
    $user   = userWithPassword('2fa-pass@boldmark.test', 'password123');
    $secret = enableTwoFactorFor($user);

    $challenge = $this->postJson(route('api.v1.auth.login'), [
        'email'    => '2fa-pass@boldmark.test',
        'password' => 'password123',
    ])->json('data.challenge');

    $res = $this->postJson(route('api.v1.auth.2fa.challenge'), [
        'challenge' => $challenge,
        'code'      => totp($secret),
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);

    expect($res->json('data.user.email'))->toBe('2fa-pass@boldmark.test');
    expect(UserSession::where('user_id', $user->id)->count())->toBe(1);

    // The issued token works on a protected route.
    $token = $res->json('data.token');
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.email', '2fa-pass@boldmark.test');
});

it('rejects a 2FA challenge with an invalid code', function () {
    $user   = userWithPassword('2fa-wrong@boldmark.test', 'password123');
    $secret = enableTwoFactorFor($user);

    $challenge = $this->postJson(route('api.v1.auth.login'), [
        'email'    => '2fa-wrong@boldmark.test',
        'password' => 'password123',
    ])->json('data.challenge');

    $valid = totp($secret);
    $wrong = $valid === '000000' ? '111111' : '000000';

    $this->postJson(route('api.v1.auth.2fa.challenge'), [
        'challenge' => $challenge,
        'code'      => $wrong,
    ])->assertStatus(422)->assertJson(['message' => 'Invalid code. Please try again.']);

    expect(DB::table('oauth_access_tokens')->count())->toBe(0);
});

it('rejects an expired 2FA challenge', function () {
    $user   = userWithPassword('2fa-stale@boldmark.test', 'password123');
    $secret = enableTwoFactorFor($user);

    $expired = Crypt::encryptString(json_encode([
        'user_id'    => $user->id,
        'remember'   => false,
        'expires_at' => now()->subMinute()->timestamp,
    ]));

    $this->postJson(route('api.v1.auth.2fa.challenge'), [
        'challenge' => $expired,
        'code'      => totp($secret),
    ])->assertStatus(422)->assertJson(['message' => 'Session expired. Please log in again.']);
});

it('rejects a tampered / unreadable 2FA challenge', function () {
    $this->postJson(route('api.v1.auth.2fa.challenge'), [
        'challenge' => 'not-a-valid-encrypted-payload',
        'code'      => '123456',
    ])->assertStatus(422);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ BM-009 — Remember me / persistent login                                  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('stores a non-persistent session by default (remember = false)', function () {
    $user = userWithPassword('no-remember@boldmark.test', 'password123');

    loginAndGetToken('no-remember@boldmark.test', 'password123');

    expect(UserSession::where('user_id', $user->id)->first()->remember)->toBeFalse();
});

it('stores a persistent session when remember is true', function () {
    $user = userWithPassword('yes-remember@boldmark.test', 'password123');

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'yes-remember@boldmark.test',
        'password' => 'password123',
        'remember' => true,
    ])->assertOk();

    expect(UserSession::where('user_id', $user->id)->first()->remember)->toBeTrue();
});

it('rejects a non-boolean remember value', function () {
    userWithPassword('remember-bad@boldmark.test', 'password123');

    $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'remember-bad@boldmark.test',
        'password' => 'password123',
        'remember' => 'maybe',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['remember']);
});

it('preserves the remember flag through the 2FA challenge', function () {
    $user   = userWithPassword('remember-2fa@boldmark.test', 'password123');
    $secret = enableTwoFactorFor($user);

    $challenge = $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'remember-2fa@boldmark.test',
        'password' => 'password123',
        'remember' => true,
    ])->json('data.challenge');

    $this->postJson(route('api.v1.auth.2fa.challenge'), [
        'challenge' => $challenge,
        'code'      => totp($secret),
    ])->assertOk();

    expect(UserSession::where('user_id', $user->id)->first()->remember)->toBeTrue();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ BM-006 — Session timeout after inactivity                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('refreshes the inactivity clock on each authenticated request', function () {
    $user  = userWithPassword('active@boldmark.test', 'password123');
    $token = loginAndGetToken('active@boldmark.test', 'password123');

    // Backdate, then make a request — activity should reset to ~now.
    UserSession::where('user_id', $user->id)->update(['last_activity_at' => now()->subMinutes(15)]);

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk();

    $session = UserSession::where('user_id', $user->id)->first();
    expect($session->last_activity_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('revokes a session that has been idle past the inactivity timeout', function () {
    config()->set('auth.session_inactivity_timeout', 30);

    $user  = userWithPassword('idle@boldmark.test', 'password123');
    $token = loginAndGetToken('idle@boldmark.test', 'password123');

    // Idle for longer than the timeout.
    UserSession::where('user_id', $user->id)->update(['last_activity_at' => now()->subMinutes(31)]);

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized()
        ->assertJson(['message' => 'Your session has expired due to inactivity. Please log in again.']);

    // Token is revoked and the session row is cleaned up.
    expect(DB::table('oauth_access_tokens')->where('revoked', true)->count())->toBe(1);
    expect(UserSession::where('user_id', $user->id)->exists())->toBeFalse();
});

it('keeps a session alive when activity is within the inactivity window', function () {
    config()->set('auth.session_inactivity_timeout', 30);

    $user  = userWithPassword('within@boldmark.test', 'password123');
    $token = loginAndGetToken('within@boldmark.test', 'password123');

    // Idle, but still inside the window.
    UserSession::where('user_id', $user->id)->update(['last_activity_at' => now()->subMinutes(15)]);

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk();
});

it('does not time out a remember-me session even after long inactivity', function () {
    config()->set('auth.session_inactivity_timeout', 30);

    $user = userWithPassword('persistent@boldmark.test', 'password123');

    $token = $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'persistent@boldmark.test',
        'password' => 'password123',
        'remember' => true,
    ])->json('data.token');

    // Far beyond the inactivity window.
    UserSession::where('user_id', $user->id)->update(['last_activity_at' => now()->subMinutes(600)]);

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ BM-011 — Direct URL access after logout                                  ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('blocks direct access to protected URLs after logout', function () {
    userWithPassword('bm011@boldmark.test', 'password123');
    $token = loginAndGetToken('bm011@boldmark.test', 'password123');

    // Sanity: the token works before logout.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk();

    // Log out.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token")
        ->postJson(route('api.v1.auth.logout'))
        ->assertOk();

    // Re-using the same token to hit protected URLs directly must now fail.
    foreach (['api.v1.auth.me', 'api.v1.sessions.index'] as $routeName) {
        forgetAuth();
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson(route($routeName))
            ->assertUnauthorized();
    }
});

it('blocks direct access to a protected URL with no token at all', function () {
    $this->getJson(route('api.v1.sessions.index'))->assertUnauthorized();
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Active sessions (SessionController) — BM-006 / BM-009 surfacing           ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('requires authentication to list sessions', function () {
    $this->getJson(route('api.v1.sessions.index'))->assertUnauthorized();
});

it('lists the current session with the new remember and last_activity_at fields', function () {
    userWithPassword('sess-list@boldmark.test', 'password123');
    $token = loginAndGetToken('sess-list@boldmark.test', 'password123');

    $res = $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.sessions.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                ['id', 'token_id', 'is_current', 'ip_address', 'user_agent', 'remember', 'last_activity_at', 'created_at'],
            ],
        ]);

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.is_current'))->toBeTrue();
    expect($res->json('data.0.remember'))->toBeFalse();
    expect($res->json('data.0.last_activity_at'))->not->toBeNull();
});

it('reports remember = true for a persistent session', function () {
    userWithPassword('sess-remember@boldmark.test', 'password123');

    $token = $this->postJson(route('api.v1.auth.login'), [
        'email'    => 'sess-remember@boldmark.test',
        'password' => 'password123',
        'remember' => true,
    ])->json('data.token');

    $this->withHeader('Authorization', "Bearer $token")
        ->getJson(route('api.v1.sessions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.remember', true);
});

it('lists multiple sessions and flags only the current one', function () {
    userWithPassword('sess-multi@boldmark.test', 'password123');
    loginAndGetToken('sess-multi@boldmark.test', 'password123');         // other device
    $current = loginAndGetToken('sess-multi@boldmark.test', 'password123'); // this device

    forgetAuth();
    $res = $this->withHeader('Authorization', "Bearer $current")
        ->getJson(route('api.v1.sessions.index'))
        ->assertOk();

    expect($res->json('data'))->toHaveCount(2);
    expect(collect($res->json('data'))->where('is_current', true))->toHaveCount(1);
});

it('excludes revoked sessions from the listing', function () {
    userWithPassword('sess-revoked@boldmark.test', 'password123');
    $token1 = loginAndGetToken('sess-revoked@boldmark.test', 'password123');
    $token2 = loginAndGetToken('sess-revoked@boldmark.test', 'password123');

    // Log token1 out → its session should drop off the list seen from token2.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token1")
        ->postJson(route('api.v1.auth.logout'))
        ->assertOk();

    forgetAuth();
    $res = $this->withHeader('Authorization', "Bearer $token2")
        ->getJson(route('api.v1.sessions.index'))
        ->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.is_current'))->toBeTrue();
});

it('revokes a specific session and the targeted token stops working', function () {
    userWithPassword('sess-destroy@boldmark.test', 'password123');
    $token1 = loginAndGetToken('sess-destroy@boldmark.test', 'password123');
    $token2 = loginAndGetToken('sess-destroy@boldmark.test', 'password123');

    // From token2, find the OTHER (token1's) session id.
    forgetAuth();
    $sessions = $this->withHeader('Authorization', "Bearer $token2")
        ->getJson(route('api.v1.sessions.index'))
        ->json('data');

    $other = collect($sessions)->firstWhere('is_current', false);

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token2")
        ->deleteJson(route('api.v1.sessions.destroy', ['session' => $other['id']]))
        ->assertOk();

    // token1 is now dead, token2 still works.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token1")
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized();

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $token2")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk();
});

it('forbids revoking a session that belongs to another user', function () {
    userWithPassword('owner-a@boldmark.test', 'password123');
    userWithPassword('owner-b@boldmark.test', 'password123');

    $tokenA = loginAndGetToken('owner-a@boldmark.test', 'password123');
    $tokenB = loginAndGetToken('owner-b@boldmark.test', 'password123');

    // B's session id, as seen by B.
    forgetAuth();
    $bSession = $this->withHeader('Authorization', "Bearer $tokenB")
        ->getJson(route('api.v1.sessions.index'))
        ->json('data.0.id');

    // A tries to revoke B's session → 403, and B still works.
    forgetAuth();
    $this->withHeader('Authorization', "Bearer $tokenA")
        ->deleteJson(route('api.v1.sessions.destroy', ['session' => $bSession]))
        ->assertForbidden();

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $tokenB")
        ->getJson(route('api.v1.auth.me'))
        ->assertOk();
});

it('revokes all other sessions but keeps the current one', function () {
    userWithPassword('sess-all@boldmark.test', 'password123');
    $token1 = loginAndGetToken('sess-all@boldmark.test', 'password123');
    $token2 = loginAndGetToken('sess-all@boldmark.test', 'password123');
    $current = loginAndGetToken('sess-all@boldmark.test', 'password123');

    forgetAuth();
    $this->withHeader('Authorization', "Bearer $current")
        ->deleteJson(route('api.v1.sessions.destroy.all'))
        ->assertOk();

    // Both other tokens are dead.
    foreach ([$token1, $token2] as $dead) {
        forgetAuth();
        $this->withHeader('Authorization', "Bearer $dead")
            ->getJson(route('api.v1.auth.me'))
            ->assertUnauthorized();
    }

    // The current token still works and is the only remaining session.
    forgetAuth();
    $res = $this->withHeader('Authorization', "Bearer $current")
        ->getJson(route('api.v1.sessions.index'))
        ->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.is_current'))->toBeTrue();
});
