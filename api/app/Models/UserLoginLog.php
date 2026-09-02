<?php

namespace App\Models;

use App\Enums\LoginFailureReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserLoginLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'login_successful',
        'failure_reason',
    ];

    protected $casts = [
        'login_successful' => 'boolean',
        'failure_reason'   => LoginFailureReason::class,
        'created_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(fn (UserLoginLog $log) => static::pruneForUser($log->user_id));
    }

    /**
     * Delete old records, keeping only the most recent N per user.
     * For null user_id (unknown email), applies a global limit across all anonymous rows.
     */
    public static function pruneForUser(?int $userId): void
    {
        if ($userId !== null) {
            $limit = (int) config('auth.login_log_per_user_limit', 100);

            if (static::where('user_id', $userId)->count() <= $limit) {
                return;
            }

            DB::table('user_login_logs')
                ->where('user_id', $userId)
                ->whereNotIn('id', fn ($q) => $q->select('id')->fromSub(
                    DB::table('user_login_logs')
                        ->select('id')
                        ->where('user_id', $userId)
                        ->orderByDesc('id')
                        ->limit($limit),
                    'keep'
                ))
                ->delete();
        } else {
            $limit = (int) config('auth.login_log_anonymous_limit', 500);

            if (static::whereNull('user_id')->count() <= $limit) {
                return;
            }

            DB::table('user_login_logs')
                ->whereNull('user_id')
                ->whereNotIn('id', fn ($q) => $q->select('id')->fromSub(
                    DB::table('user_login_logs')
                        ->select('id')
                        ->whereNull('user_id')
                        ->orderByDesc('id')
                        ->limit($limit),
                    'keep'
                ))
                ->delete();
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
