<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalGroup extends Model
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
        $query->whereLike('name', $searchTerm);
    }

    /**
     * Get the community this group belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the organization this group belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Restrict route-model binding to the authenticated user's organization.
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
