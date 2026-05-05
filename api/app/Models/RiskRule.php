<?php

namespace App\Models;

use App\Enums\RiskSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RiskRule extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'name'       => 'string',
        'severity'   => RiskSeverity::class,
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'conditions' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'description', 'severity', 'conditions', 'is_active', 'sort_order', 'organization_id',
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
     * Scope a query to only active rules.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get tenant.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

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
