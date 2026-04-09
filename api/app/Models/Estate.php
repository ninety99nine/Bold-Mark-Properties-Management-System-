<?php

namespace App\Models;

use App\Enums\EstateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Estate extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type'                 => EstateType::class,
        'is_active'            => 'boolean',
        'default_levy_amount'  => 'float',
        'default_rent_amount'  => 'float',
        'billing_day'          => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'address',
        'type',
        'is_active',
        'default_levy_amount',
        'default_rent_amount',
        'billing_day',
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
        $query->where('name', 'like', '%' . $searchTerm . '%')
              ->orWhere('address', 'like', '%' . $searchTerm . '%');
    }

    /**
     * Scope to active estates only.
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
     * Get the tenant (organisation) this estate belongs to.
     *
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get units within this estate.
     *
     * @return HasMany
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get active units within this estate.
     *
     * @return HasMany
     */
    public function activeUnits(): HasMany
    {
        return $this->hasMany(Unit::class)->where('status', 'active');
    }

    /**
     * Get cashbook entries for this estate.
     *
     * @return HasMany
     */
    public function cashbookEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class);
    }

    /**
     * Get charge types enabled for this estate.
     *
     * @return BelongsToMany
     */
    public function chargeTypes(): BelongsToMany
    {
        return $this->belongsToMany(ChargeType::class, 'estate_charge_types')
                    ->withPivot(['id', 'is_active'])
                    ->using(EstateChargeType::class)
                    ->as('estate_charge_type')
                    ->withTimestamps();
    }

    /**
     * Get active charge types enabled for this estate.
     *
     * @return BelongsToMany
     */
    public function activeChargeTypes(): BelongsToMany
    {
        return $this->belongsToMany(ChargeType::class, 'estate_charge_types')
                    ->withPivot(['id', 'is_active'])
                    ->using(EstateChargeType::class)
                    ->as('estate_charge_type')
                    ->wherePivot('is_active', true)
                    ->withTimestamps();
    }

    /**
     * Get estate charge type junction records.
     *
     * @return HasMany
     */
    public function estateChargeTypes(): HasMany
    {
        return $this->hasMany(EstateChargeType::class);
    }

    /**
     * Get staff users assigned to this estate.
     *
     * @return BelongsToMany
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_estates')->withTimestamps();
    }
}
