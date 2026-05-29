<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * List all active (non-revoked) sessions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user           = $request->user();
        $currentTokenId = $user->token()->id;

        $sessions = UserSession::where('user_id', $user->id)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('oauth_access_tokens')
                    ->whereColumn('oauth_access_tokens.id', 'user_sessions.token_id')
                    ->where('oauth_access_tokens.revoked', false);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'token_id'   => $s->token_id,
                'is_current' => $s->token_id === $currentTokenId,
                'ip_address' => $s->ip_address ?? '—',
                'user_agent' => $s->user_agent,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $sessions]);
    }

    /**
     * Revoke a specific session (by user_session id).
     */
    public function destroy(Request $request, UserSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        DB::table('oauth_access_tokens')
            ->where('id', $session->token_id)
            ->update(['revoked' => true]);

        $session->delete();

        return response()->json(['message' => 'Session revoked.']);
    }

    /**
     * Revoke all sessions except the current one.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user           = $request->user();
        $currentTokenId = $user->token()->id;

        $tokenIds = UserSession::where('user_id', $user->id)
            ->where('token_id', '!=', $currentTokenId)
            ->pluck('token_id')
            ->toArray();

        if (!empty($tokenIds)) {
            DB::table('oauth_access_tokens')
                ->whereIn('id', $tokenIds)
                ->update(['revoked' => true]);

            UserSession::whereIn('token_id', $tokenIds)->delete();
        }

        return response()->json(['message' => 'All other sessions revoked.']);
    }
}
