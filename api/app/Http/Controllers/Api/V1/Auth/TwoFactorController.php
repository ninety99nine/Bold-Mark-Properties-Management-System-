<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Generate a new TOTP secret and return the QR URI for setup.
     * The secret is NOT saved until the user confirms with a valid code.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->google2fa->generateSecretKey();

        // Store the pending secret encrypted in cache until confirmed
        cache()->put(
            "2fa_pending:{$user->id}",
            $secret,
            now()->addMinutes(15)
        );

        return response()->json([
            'data' => [
                'qr_uri' => $this->buildQrUri($user, $secret),
                'secret' => $secret,
                'enabled' => $user->hasTwoFactorEnabled(),
            ],
        ]);
    }

    /**
     * Confirm the pending 2FA setup by verifying the user's first TOTP code.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $user = $request->user();
        $secret = cache()->get("2fa_pending:{$user->id}");

        if (! $secret) {
            return response()->json(['message' => 'Setup session expired. Please start again.'], 422);
        }

        $valid = $this->google2fa->verifyKey($secret, $request->input('code'));

        if (! $valid) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        cache()->forget("2fa_pending:{$user->id}");

        return response()->json(['message' => 'Two-factor authentication enabled.']);
    }

    /**
     * Start the forced 2FA enrollment that happens during login for a user who
     * has not yet set up two-factor authentication. Gated by the encrypted
     * "setup" challenge issued by AuthController@login (no bearer token yet).
     * Generates a pending secret and returns the QR URI for the authenticator app.
     */
    public function enrollStart(Request $request): JsonResponse
    {
        $request->validate(['challenge' => ['required', 'string']]);

        $user = $this->resolveChallenge($request->input('challenge'), 'setup');

        if (! $user) {
            return response()->json(['message' => 'Invalid or expired session. Please log in again.'], 422);
        }

        $secret = $this->google2fa->generateSecretKey();

        cache()->put("2fa_pending:{$user->id}", $secret, now()->addMinutes(15));

        return response()->json([
            'data' => [
                'qr_uri' => $this->buildQrUri($user, $secret),
                'secret' => $secret,
            ],
        ]);
    }

    /**
     * Complete forced enrollment during login: verify the user's first TOTP
     * code, persist the secret, and — only now that the second factor is set
     * up — issue the access token. This both enables 2FA and finishes login.
     */
    public function enrollConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $payload = $this->decodeChallenge($request->input('challenge'), 'setup');

        if (! $payload) {
            return response()->json(['message' => 'Invalid or expired session. Please log in again.'], 422);
        }

        $user = User::find($payload['user_id']);
        $secret = $user ? cache()->get("2fa_pending:{$user->id}") : null;

        if (! $user || ! $secret) {
            return response()->json(['message' => 'Setup session expired. Please log in again.'], 422);
        }

        if (! $this->google2fa->verifyKey($secret, $request->input('code'))) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        cache()->forget("2fa_pending:{$user->id}");

        return $this->issueSession($user, (bool) ($payload['remember'] ?? false), $request);
    }

    /**
     * Verify a TOTP code during the login 2FA challenge.
     * Expects: challenge (encrypted user_id + expiry) and code.
     */
    public function challenge(Request $request): JsonResponse
    {
        $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $payload = $this->decodeChallenge($request->input('challenge'), 'login');

        if (! $payload) {
            return response()->json(['message' => 'Invalid or expired session.'], 422);
        }

        $user = User::find($payload['user_id']);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Invalid session.'], 422);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        if (! $this->google2fa->verifyKey($secret, $request->input('code'))) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        return $this->issueSession($user, (bool) ($payload['remember'] ?? false), $request);
    }

    /**
     * Build the otpauth:// QR URI for an authenticator app. Uses a clean,
     * branded issuer label and — for apps that support the non-standard "image"
     * parameter — embeds the BoldMark logo. The TOTP standard works with
     * Microsoft Authenticator and Apple Passwords (Verification Codes); SMS is
     * never used.
     */
    private function buildQrUri(User $user, string $secret): string
    {
        $issuer = config('app.two_factor_issuer', config('app.name', 'BoldMark PMS'));

        $uri = $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);

        // Reuse the BoldMark symbol the app already ships (served at the
        // frontend root) unless an explicit override is configured. A square
        // symbol reads better as an authenticator icon than the wordmark.
        // Authenticator apps that support the non-standard "image" parameter
        // render it.
        $logo = config('app.two_factor_logo_url')
            ?: rtrim((string) config('app.frontend_url'), '/') . '/authenticator-icon.png';

        if ($logo) {
            $uri .= '&image=' . urlencode($logo);
        }

        return $uri;
    }

    /**
     * Decode and validate an encrypted login challenge for the expected purpose.
     * Returns the payload array, or null if the token is malformed, tampered
     * with, expired, or issued for a different purpose.
     */
    private function decodeChallenge(string $challenge, string $expectedPurpose): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($challenge), true);
        } catch (\Exception) {
            return null;
        }

        if (! $payload || ! isset($payload['user_id'], $payload['expires_at'], $payload['purpose'])) {
            return null;
        }

        if ($payload['purpose'] !== $expectedPurpose || now()->timestamp > $payload['expires_at']) {
            return null;
        }

        return $payload;
    }

    /**
     * Resolve the user referenced by a challenge of the given purpose.
     */
    private function resolveChallenge(string $challenge, string $expectedPurpose): ?User
    {
        $payload = $this->decodeChallenge($challenge, $expectedPurpose);

        return $payload ? User::find($payload['user_id']) : null;
    }

    /**
     * Issue an access token + session once both authentication factors have
     * been satisfied, activating an invited account on first sign-in.
     */
    private function issueSession(User $user, bool $remember, Request $request): JsonResponse
    {
        $attributes = ['last_login_at' => now()];

        if ($user->status === UserStatus::INVITED) {
            $attributes['status'] = UserStatus::ACTIVE;
        }

        $user->update($attributes);

        $tokenResult = $user->createToken('api-token');

        UserSession::create([
            'user_id' => $user->id,
            'token_id' => $tokenResult->token->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_activity_at' => now(),
            'remember' => $remember,
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $tokenResult->accessToken,
            ],
        ]);
    }
}
