<?php

namespace App\Models;

use App\Enums\OccupancyType;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'occupancy_type'  => OccupancyType::class,
        'status'          => UnitStatus::class,
        'pq'              => 'float',
        'levy_override'   => 'float',
        'rent_amount'     => 'float',
        'balance'         => 'float',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'unit_number',
        'section',
        'address',
        'pq',
        'occupancy_type',
        'status',
        'levy_override',
        'rent_amount',
        'balance',
        'estate_id',
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
        $query->where(function (Builder $q) use ($searchTerm) {
            $q->whereLike('units.unit_number', $searchTerm)
              ->orWhereLike('units.address', $searchTerm)
              ->orWhereHas('owner', fn (Builder $o) => $o->whereLike('full_name', $searchTerm)->orWhereLike('email', $searchTerm));
        });
    }

    /**
     * Scope to active units only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', UnitStatus::ACTIVE);
    }

    /**
     * Scope to tenant-occupied units only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function tenantOccupied(Builder $query): void
    {
        $query->where('occupancy_type', OccupancyType::TENANT_OCCUPIED);
    }

    /**
     * Scope to owner-occupied units only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function ownerOccupied(Builder $query): void
    {
        $query->where('occupancy_type', OccupancyType::OWNER_OCCUPIED);
    }

    /**
     * Scope to vacant units only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function vacant(Builder $query): void
    {
        $query->where('occupancy_type', OccupancyType::VACANT);
    }

    /**
     * Get the estate this unit belongs to.
     *
     * @return BelongsTo
     */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Get the tenant (organisation) this unit belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the owner of this unit.
     *
     * @return HasOne
     */
    public function owner(): HasOne
    {
        return $this->hasOne(Owner::class);
    }

    /**
     * Get the current active tenant (occupant) of this unit.
     *
     * Uses a plain HasOne + where instead of latestOfMany/ofMany because
     * Laravel's ofMany always emits MAX(id) as a tiebreaker, which fails on
     * PostgreSQL when the primary key is a UUID. Since business logic ensures
     * at most one is_active tenant exists per unit at a time, a simple where
     * clause is both correct and safe.
     *
     * @return HasOne
     */
    public function currentTenant(): HasOne
    {
        return $this->hasOne(Tenant::class)->where('is_active', true);
    }

    /**
     * Get all tenant (occupant) history for this unit.
     *
     * @return HasMany
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Get per-unit recurring charge configurations.
     *
     * @return HasMany
     */
    public function chargeConfigs(): HasMany
    {
        return $this->hasMany(UnitChargeConfig::class);
    }

    /**
     * Get active charge configurations for this unit.
     *
     * @return HasMany
     */
    public function activeChargeConfigs(): HasMany
    {
        return $this->hasMany(UnitChargeConfig::class)->where('is_active', true);
    }

    /**
     * Get invoices raised against this unit.
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get cashbook entries allocated to this unit.
     *
     * @return HasMany
     */
    public function cashbookEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class);
    }

    /**
     * Get the effective admin levy amount for this unit using PQ formula.
     * Falls back to equal-share division, then levy_override if set.
     *
     * @return float|null
     */
    public function getEffectiveLevyAmountAttribute(): ?float
    {
        if ($this->levy_override !== null) {
            return $this->levy_override;
        }

        if ($this->pq !== null && $this->relationLoaded('estate') && $this->estate !== null) {
            $totalPq = $this->estate->units()->whereNotNull('pq')->sum('pq');
            $budget  = (float) ($this->estate->admin_fund_amount ?? 0);
            if ($totalPq > 0 && $budget > 0) {
                return round(($this->pq / $totalPq) * $budget, 2);
            }
        }

        $unitCount = $this->estate?->units()->count() ?? 1;

        return $unitCount > 0 ? round(((float) ($this->estate?->admin_fund_amount ?? 0)) / $unitCount, 2) : null;
    }

    /**
     * Get the effective reserve levy amount using PQ formula.
     *
     * @return float|null
     */
    public function getEffectiveReserveLevyAttribute(): ?float
    {
        if ($this->pq !== null && $this->relationLoaded('estate') && $this->estate !== null) {
            $totalPq = $this->estate->units()->whereNotNull('pq')->sum('pq');
            $budget  = (float) ($this->estate->reserve_fund_amount ?? 0);
            if ($totalPq > 0 && $budget > 0) {
                return round(($this->pq / $totalPq) * $budget, 2);
            }
        }

        $unitCount = $this->estate?->units()->count() ?? 1;

        return $unitCount > 0 ? round(((float) ($this->estate?->reserve_fund_amount ?? 0)) / $unitCount, 2) : null;
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $query = $this->where($field ?? $this->getRouteKeyName(), $value)
                      ->where('organization_id', $user->organization_id);

        $estate = request()->route('estate');
        if ($estate instanceof Estate) {
            $query->where('estate_id', $estate->id);
        }

        return $query->first();
    }
}
