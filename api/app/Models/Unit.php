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
        'address',
        'occupancy_type',
        'status',
        'levy_override',
        'rent_amount',
        'balance',
        'estate_id',
        'tenant_id',
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
        $term = '%' . $searchTerm . '%';

        $query->where(function (Builder $q) use ($term) {
            $q->where('units.unit_number', 'like', $term)
              ->orWhere('units.address', 'like', $term)
              ->orWhereHas('owner', fn (Builder $o) => $o->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
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
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
        return $this->hasOne(UnitTenant::class)->where('is_active', true);
    }

    /**
     * Get all tenant (occupant) history for this unit.
     *
     * @return HasMany
     */
    public function unitTenants(): HasMany
    {
        return $this->hasMany(UnitTenant::class);
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
     * Get the effective levy amount: override if set, otherwise estate default.
     *
     * @return float|null
     */
    public function getEffectiveLevyAmountAttribute(): ?float
    {
        return $this->levy_override ?? $this->estate?->default_levy_amount;
    }
}
