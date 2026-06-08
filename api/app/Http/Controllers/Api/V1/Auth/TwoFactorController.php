<?php

namespace App\Http\Controllers\Api\V1\Auth;

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

        $qrUri = $this->google2fa->getQRCodeUrl(
            config('app.name', 'BoldMark PMS'),
            $user->email,
            $secret
        );

        return response()->json([
            'data' => [
                'qr_uri' => $qrUri,
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
     * Disable 2FA for the authenticated user.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json(['message' => '2FA is not enabled.'], 422);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $request->input('code'));

        if (! $valid) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['message' => 'Two-factor authentication disabled.']);
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

        try {
            $payload = json_decode(Crypt::decryptString($request->input('challenge')), true);
        } catch (\Exception) {
            return response()->json(['message' => 'Invalid or expired session.'], 422);
        }

        if (! $payload || ! isset($payload['user_id'], $payload['expires_at'])) {
            return response()->json(['message' => 'Invalid challenge.'], 422);
        }

        if (now()->timestamp > $payload['expires_at']) {
            return response()->json(['message' => 'Session expired. Please log in again.'], 422);
        }

        $user = User::find($payload['user_id']);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Invalid session.'], 422);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $request->input('code'));

        if (! $valid) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->update(['last_login_at' => now()]);

        $tokenResult = $user->createToken('api-token');

        UserSession::create([
            'user_id' => $user->id,
            'token_id' => $tokenResult->token->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_activity_at' => now(),
            'remember' => (bool) ($payload['remember'] ?? false),
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
