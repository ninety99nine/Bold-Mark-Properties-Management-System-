<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitStatusHistory extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_status_histories';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status'       => 'string',
        'status_date'  => 'date',
        'is_automatic' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'status',
        'note',
        'status_date',
        'is_automatic',
        'changed_by_name',
        'unit_id',
        'community_id',
        'organization_id',
        'user_id',
    ];

    /**
     * Get the unit (customer) this status change belongs to.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
