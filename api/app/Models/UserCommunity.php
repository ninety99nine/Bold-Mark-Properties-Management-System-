<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCommunity extends Pivot
{
    use HasUuids;

    protected $table = 'user_communities';

    public $incrementing = false;

    protected $keyType = 'string';
}
