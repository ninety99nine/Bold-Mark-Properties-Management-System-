<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a sliding-window inactivity timeout on API sessions (BM-006).
 *
 * Passport access tokens do not expire on their own. This middleware tracks
 * the last activity timestamp per session (UserSession) and revokes a token
 * once it has been idle longer than `auth.session_inactivity_timeout` minutes.
 * Any authenticated request resets the clock.
 *
 * Sessions created with "remember me" (BM-009) are exempt from the timeout,
 * but their last-activity timestamp is still kept current.
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('api')->user();
        $token = $user?->token();

        // Skip when there is no real persisted access token to enforce against:
        // unauthenticated requests, or requests authenticated via actingAs()
        // (which yields a TransientToken with no backing oauth_access_tokens row).
        if (! $token || $token instanceof TransientToken || empty($token->id)) {
            return $next($request);
        }

        $session = UserSession::where('token_id', $token->id)->first();

        // Token issued without a tracked session — nothing to enforce.
        if (! $session) {
            return $next($request);
        }

        $timeout = (int) config('auth.session_inactivity_timeout', 30);

        $expired = ! $session->remember
            && $timeout > 0
            && $session->last_activity_at
            && $session->last_activity_at->lt(now()->subMinutes($timeout));

        if ($expired) {
            $token->revoke();
            $session->delete();

            return response()->json([
                'message' => 'Your session has expired due to inactivity. Please log in again.',
            ], 401);
        }

        // Reset the inactivity clock for this request.
        $session->forceFill(['last_activity_at' => now()])->save();

        return $next($request);
    }
}
