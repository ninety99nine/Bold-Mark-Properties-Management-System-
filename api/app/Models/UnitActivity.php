<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitActivity extends Model
{
    use HasUuids;

    protected $table = 'unit_activities';

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'batch_id',
        'user_id',
        'changed_by_name',
        'event',
        'category',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
