<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginLogController extends Controller
{
    public function showUserLoginLogs(User $user): JsonResponse
    {
        $logs = UserLoginLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($logs);
    }

    public function showAllLoginLogs(Request $request): JsonResponse
    {
        $tenantUserIds = User::where('organization_id', Auth::user()->organization_id)
            ->pluck('id');

        $query = UserLoginLog::with('user:id,name,email')
            ->whereIn('user_id', $tenantUserIds)
            ->orderByDesc('created_at');

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        if ($request->filled('status')) {
            $query->where('login_successful', $request->status === 'success');
        }

        if ($request->filled('failure_reason')) {
            $query->where('failure_reason', $request->failure_reason);
        }

        return response()->json($query->paginate(50));
    }
}
