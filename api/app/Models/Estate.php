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
        'admin_fund_amount'    => 'float',
        'reserve_fund_amount'  => 'float',
        'csos_levy_amount'     => 'float',
        'default_rent_amount'  => 'float',
        'billing_day'             => 'integer',
        'payment_terms_days'      => 'integer',
        'payment_reminder_days'   => 'integer',
        'billing_paused'          => 'boolean',
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
        'admin_fund_amount',
        'reserve_fund_amount',
        'csos_levy_amount',
        'default_rent_amount',
        'billing_day',
        'payment_terms_days',
        'payment_reminder_days',
        'billing_paused',
        'country',
        'currency',
        'registration_number',
        'csos_registration_number',
        'income_tax_number',
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
        $query->whereLike('name', $searchTerm)
              ->orWhereLike('address', $searchTerm);
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
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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
     * Get compliance checklists for this estate.
     *
     * @return HasMany
     */
    public function complianceChecklists(): HasMany
    {
        return $this->hasMany(ComplianceChecklist::class);
    }

    /**
     * Get staff users assigned to this estate.
     *
     * @return BelongsToMany
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_estates')
                    ->using(UserEstate::class)
                    ->withTimestamps();
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
