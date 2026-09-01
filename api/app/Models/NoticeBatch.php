<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NoticeBatch extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'ageing_date' => 'date',
        'total'       => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ageing_date',
        'total',
        'created_by_name',
        'user_id',
        'community_id',
        'organization_id',
    ];

    /**
     * Get the items (per-customer notices) in this batch, newest first.
     *
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(NoticeBatchItem::class)->orderBy('customer_code');
    }

    /**
     * Get the community this batch belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the organization this batch belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope route-model binding to the authenticated user's organization.
     *
     * @param mixed $value
     * @param string|null $field
     * @return NoticeBatch|null
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('organization_id', $user->organization_id)
            ->first();
    }
}
