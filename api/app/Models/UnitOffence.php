<?php

namespace App\Models;

use App\Enums\OffenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitOffence extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status'           => OffenceStatus::class,
        'issued_date'      => 'date',
        'rules'            => 'array',
        'attachment_names' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'status',
        'issued_date',
        'description',
        'rules',
        'attachment_names',
        'created_by_name',
        'user_id',
        'unit_id',
        'organization_id',
    ];

    /**
     * Get the unit this offence belongs to.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the organization this offence belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
