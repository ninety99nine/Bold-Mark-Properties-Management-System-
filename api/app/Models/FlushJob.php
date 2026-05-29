<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FlushJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'status',
        'steps',
        'error',
    ];

    protected $casts = [
        'steps' => 'array',
    ];
}
