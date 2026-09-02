<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token_id',
        'ip_address',
        'user_agent',
        'last_activity_at',
        'remember',
        'created_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'remember' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
