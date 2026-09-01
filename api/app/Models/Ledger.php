<?php

namespace App\Models;

use App\Enums\LedgerAppliesTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ledger extends Model
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
        'applies_to'   => LedgerAppliesTo::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'category',
        'type',
        'name',
        'description',
        'is_system',
        'is_active',
        'is_recurring',
        'sort_order',
        'applies_to',
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
        $query->where('name', 'like', '%' . $searchTerm . '%');
    }

    /**
     * Scope to active ledgers only.
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
     * Scope to recurring ledgers only.
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
     * Scope to ad-hoc (non-recurring) ledgers only.
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
     * Get the occupant (organisation) this ledger belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get communities that have this ledger enabled.
     *
     * @return BelongsToMany
     */
    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class, 'community_ledgers')
                    ->withPivot(['id', 'is_active'])
                    ->using(CommunityLedger::class)
                    ->as('community_ledger')
                    ->withTimestamps();
    }

    /**
     * Get unit charge configurations for this ledger.
     *
     * @return HasMany
     */
    public function unitChargeConfigs(): HasMany
    {
        return $this->hasMany(UnitChargeConfig::class);
    }

    /**
     * Get invoices raised for this ledger.
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get cashbook entries tagged with this ledger.
     *
     * @return HasMany
     */
    public function cashbookEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class);
    }
}
