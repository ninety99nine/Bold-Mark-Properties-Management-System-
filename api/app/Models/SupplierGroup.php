<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierGroup extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'name' => 'string',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'community_id',
        'organization_id',
    ];

    /**
     * Scope a query by search term.
     *
     * @param Builder $query
     * @param string $searchTerm
     * @return void
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $query->where(function (Builder $query) use ($searchTerm) {
            $query->whereLike('name', $searchTerm);
        });
    }

    /**
     * Scope supplier groups visible to a community (community-specific or org-shared).
     *
     * @param Builder $query
     * @param string $communityId
     * @return void
     */
    #[Scope]
    protected function forCommunity(Builder $query, string $communityId): void
    {
        $query->where(function (Builder $query) use ($communityId) {
            $query->where('community_id', $communityId)
                  ->orWhereNull('community_id');
        });
    }

    /**
     * Get the community this group belongs to (null = organisation-wide).
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the occupant (organisation) this group belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the suppliers in this group.
     *
     * @return HasMany
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /**
     * Resolve route binding scoped to the authenticated user's organisation.
     *
     * @param mixed $value
     * @param string|null $field
     * @return self|null
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
