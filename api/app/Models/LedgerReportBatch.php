<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LedgerReportBatch extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date_from'     => 'date',
        'date_to'       => 'date',
        'account_count' => 'integer',
        'generated_at'  => 'datetime',
        'filters'       => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date_from',
        'date_to',
        'status',
        'account_count',
        'generated_at',
        'filters',
        'community_id',
        'requested_by_user_id',
        'organization_id',
    ];

    /**
     * Get the community this report belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
