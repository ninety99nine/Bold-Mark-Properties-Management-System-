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

    /**
     * The name of the user who most recently applied each status, keyed
     * [unit_id][status]. Used to label who set a customer's current collection
     * status — e.g. the "Handed over to Attorneys (name)" marker, where the name
     * is the acting user, not the customer.
     *
     * @param array<string> $unitIds
     * @return array<string, array<string, string|null>>
     */
    public static function latestActorsByUnit(array $unitIds): array
    {
        if (empty($unitIds)) {
            return [];
        }

        return static::whereIn('unit_id', $unitIds)
            ->orderByDesc('status_date')
            ->orderByDesc('created_at')
            ->get(['unit_id', 'status', 'changed_by_name'])
            ->groupBy('unit_id')
            ->map(fn ($rows) => $rows->groupBy('status')
                ->map(fn ($group) => $group->first()->changed_by_name)
                ->toArray())
            ->toArray();
    }
}
