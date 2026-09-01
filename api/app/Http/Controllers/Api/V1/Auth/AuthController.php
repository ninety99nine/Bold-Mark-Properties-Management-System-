<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\LoginFailureReason;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $email = $request->input('email');
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->logLoginAttempt($request, null, false, LoginFailureReason::USER_NOT_FOUND);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            $this->logLoginAttempt($request, $user, false, LoginFailureReason::WRONG_PASSWORD);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === UserStatus::INACTIVE) {
            $this->logLoginAttempt($request, $user, false, LoginFailureReason::ACCOUNT_INACTIVE);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === UserStatus::INVITED) {
            $this->logLoginAttempt($request, $user, false, LoginFailureReason::ACCOUNT_INVITED);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Auth::login($user);

        $this->logLoginAttempt($request, $user, true, null);

        // Two-factor authentication is mandatory for every user and can never be
        // bypassed (BUG-003). A correct password is only the first factor — no
        // access token is issued here under any circumstance. We mint a short-lived
        // encrypted challenge and hand the user to the appropriate second step:
        //   - enrolled users          -> verify a TOTP code (two_factor_required)
        //   - users without 2FA yet   -> set up 2FA now    (two_factor_setup_required)
        $payload = [
            'user_id'    => $user->id,
            'remember'   => $remember,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ];

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'data' => [
                    'two_factor_required' => true,
                    'challenge' => Crypt::encryptString(json_encode($payload + ['purpose' => 'login'])),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'two_factor_setup_required' => true,
                'challenge' => Crypt::encryptString(json_encode($payload + ['purpose' => 'setup'])),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->load('roles', 'permissions'),
        ]);
    }

    /**
     * Lightweight keep-alive ping (BM-006). Reaching this method means the `api`
     * middleware group's EnforceSessionTimeout has already refreshed this
     * session's last-activity timestamp for the current request, so an
     * actively-used session stays alive. Returns 204 — nothing to send back.
     */
    public function heartbeat(): Response
    {
        return response()->noContent();
    }

    public function logout(Request $request): JsonResponse
    {
        $tokenId = $request->user()->token()->id;

        $request->user()->token()->revoke();

        UserSession::where('token_id', $tokenId)->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $brokerName = in_array($request->input('broker'), ['users', 'invitations'])
            ? $request->input('broker')
            : 'users';

        $status = Password::broker($brokerName)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($brokerName) {
                $attributes = ['password' => $password];

                // Completing an invitation activates the account so the user can
                // reach the login gate, where they are forced to set up 2FA.
                if ($brokerName === 'invitations' && $user->status === UserStatus::INVITED) {
                    $attributes['status'] = UserStatus::ACTIVE;
                }

                $user->forceFill($attributes)->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => __($status)]);
    }

    private function logLoginAttempt(
        Request $request,
        ?User $user,
        bool $successful,
        ?LoginFailureReason $reason
    ): void {
        UserLoginLog::create([
            'user_id' => $user?->id,
            'email' => $request->input('email'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_successful' => $successful,
            'failure_reason' => $reason,
        ]);
    }
}
