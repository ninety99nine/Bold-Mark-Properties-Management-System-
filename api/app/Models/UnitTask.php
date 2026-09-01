<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitTask extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status'           => TaskStatus::class,
        'internal'         => 'boolean',
        'due_date'         => 'date',
        'contacts'         => 'array',
        'supplier_names'   => 'array',
        'attachment_names' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'title',
        'description',
        'category',
        'task_type',
        'area',
        'recurring_type',
        'assignee_name',
        'status',
        'internal',
        'due_date',
        'contacts',
        'supplier_names',
        'attachment_names',
        'created_by_name',
        'assignee_user_id',
        'user_id',
        'unit_id',
        'community_id',
        'organization_id',
    ];

    /**
     * Get the unit this task belongs to.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the community this task belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Whether the task is past its due date and not yet complete.
     */
    public function getIsOverdueAttribute(): bool
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return $status !== TaskStatus::COMPLETE->value
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    /**
     * Get the organization this task belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the update/feedback log for this task, newest first.
     *
     * @return HasMany
     */
    public function updates(): HasMany
    {
        return $this->hasMany(UnitTaskUpdate::class)->orderByDesc('created_at');
    }
}
