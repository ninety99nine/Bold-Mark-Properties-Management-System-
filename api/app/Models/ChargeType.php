<?php

namespace App\Models;

use App\Enums\ChargeTypeAppliesTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChargeType extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_system'    => 'boolean',
        'is_active'    => 'boolean',
        'is_recurring' => 'boolean',
        'sort_order'   => 'integer',
        'applies_to'   => ChargeTypeAppliesTo::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
        'is_active',
        'is_recurring',
        'sort_order',
        'applies_to',
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
              ->orWhere('code', 'like', '%' . $searchTerm . '%');
    }

    /**
     * Scope to active charge types only.
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
     * Scope to recurring charge types only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function recurring(Builder $query): void
    {
        $query->where('is_recurring', true);
    }

    /**
     * Scope to ad-hoc (non-recurring) charge types only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function adHoc(Builder $query): void
    {
        $query->where('is_recurring', false);
    }

    /**
     * Get the tenant (organisation) this charge type belongs to.
     *
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get estates that have this charge type enabled.
     *
     * @return BelongsToMany
     */
    public function estates(): BelongsToMany
    {
        return $this->belongsToMany(Estate::class, 'estate_charge_types')
                    ->withPivot(['id', 'is_active'])
                    ->using(EstateChargeType::class)
                    ->as('estate_charge_type')
                    ->withTimestamps();
    }

    /**
     * Get unit charge configurations for this charge type.
     *
     * @return HasMany
     */
    public function unitChargeConfigs(): HasMany
    {
        return $this->hasMany(UnitChargeConfig::class);
    }

    /**
     * Get invoices raised for this charge type.
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get cashbook entries tagged with this charge type.
     *
     * @return HasMany
     */
    public function cashbookEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class);
    }
}
