<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\LoginFailureReason;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $email    = $request->input('email');
        $password = $request->input('password');

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

        $user->update(['last_login_at' => now()]);

        $this->logLoginAttempt($request, $user, true, null);

        $token = $user->createToken('api-token')->accessToken;

        return response()->json([
            'data' => [
                'user'  => $user,
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->load('roles', 'permissions'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

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
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $brokerName = in_array($request->input('broker'), ['users', 'invitations'])
            ? $request->input('broker')
            : 'users';

        $status = Password::broker($brokerName)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
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
            'user_id'          => $user?->id,
            'email'            => $request->input('email'),
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'login_successful' => $successful,
            'failure_reason'   => $reason,
        ]);
    }
}
