<?php

namespace App\Models;

use App\Enums\CollectionStatus;
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
        'ratio_1'         => 'float',
        'ratio_2'         => 'float',
        'ratio_3'         => 'float',
        'ratio_4'         => 'float',
        'ratio_5'         => 'float',
        'unit_size'       => 'float',
        'garage_size'     => 'float',
        'carport_size'    => 'float',
        'parking_size'    => 'float',
        'levy_override'   => 'float',
        'rent_amount'     => 'float',
        'balance'         => 'float',
        'billing_pdf'     => 'boolean',
        'is_development'   => 'boolean',
        'debit_order'      => 'boolean',
        'transfer_active'  => 'boolean',
        'collection_status' => CollectionStatus::class,
        'interest_exempt'  => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'unit_number',
        'block_number',
        'section',
        'door_number',
        'customer_code',
        'address',
        'rental_agent_email',
        'attorney_email',
        'bondholder_email',
        'unit_notes',
        'pq',
        'ratio_1',
        'ratio_2',
        'ratio_3',
        'ratio_4',
        'ratio_5',
        'unit_size',
        'garage_size',
        'carport_size',
        'parking_size',
        'occupancy_type',
        'status',
        'levy_override',
        'rent_amount',
        'billing_pdf',
        'is_development',
        'collection_status',
        'interest_exempt',
        'debit_order',
        'transfer_active',
        'balance',
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
        $query->where(function (Builder $q) use ($searchTerm) {
            $q->whereLike('units.unit_number', $searchTerm)
              ->orWhereLike('units.block_number', $searchTerm)
              ->orWhereLike('units.door_number', $searchTerm)
              ->orWhereLike('units.customer_code', $searchTerm)
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
     * Scope to occupant-occupied units only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function occupantOccupied(Builder $query): void
    {
        $query->where('occupancy_type', OccupancyType::OCCUPANT_OCCUPIED);
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
     * Get the community this unit belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the occupant (organisation) this unit belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the PRIMARY owner of this unit.
     *
     * A unit can have multiple owners (see owners()); the primary owner is the one
     * used for the customer code, list display, exports and billing. Falls back to
     * any owner when none is explicitly flagged primary (legacy rows).
     *
     * @return HasOne
     */
    public function owner(): HasOne
    {
        return $this->hasOne(Owner::class)->orderByDesc('is_primary')->orderBy('created_at');
    }

    /**
     * Get all owners of this unit (WeConnectU supports multiple owners per unit).
     *
     * @return HasMany
     */
    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class)->orderByDesc('is_primary')->orderBy('created_at');
    }

    /**
     * Get the unit's collection notes (finances Notes log), newest first.
     *
     * @return HasMany
     */
    public function collectionNotes(): HasMany
    {
        return $this->hasMany(UnitCollectionNote::class)->orderByDesc('created_at');
    }

    /**
     * Get the unit's collection-status history (Status Management), newest first.
     *
     * @return HasMany
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(UnitStatusHistory::class)->orderByDesc('status_date');
    }

    /**
     * Get the unit's communication (e-mail) log, newest first.
     *
     * @return HasMany
     */
    public function communications(): HasMany
    {
        return $this->hasMany(UnitCommunication::class)->orderByDesc('created_at');
    }

    /**
     * Get the unit's offences (conduct-rule violations), newest first.
     *
     * @return HasMany
     */
    public function offences(): HasMany
    {
        return $this->hasMany(UnitOffence::class)->orderByDesc('issued_date')->orderByDesc('created_at');
    }

    /**
     * Get the unit's tasks (maintenance / action items), newest first.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(UnitTask::class)->orderByDesc('created_at');
    }

    /**
     * Get the unit's uploaded documents, newest first.
     *
     * @return HasMany
     */
    public function documents(): HasMany
    {
        return $this->hasMany(UnitDocument::class)->orderByDesc('created_at');
    }

    /**
     * Get the current active occupant (occupant) of this unit.
     *
     * Uses a plain HasOne + where instead of latestOfMany/ofMany because
     * Laravel's ofMany always emits MAX(id) as a tiebreaker, which fails on
     * PostgreSQL when the primary key is a UUID. Since business logic ensures
     * at most one is_active occupant exists per unit at a time, a simple where
     * clause is both correct and safe.
     *
     * @return HasOne
     */
    public function currentOccupant(): HasOne
    {
        return $this->hasOne(Occupant::class)->where('is_active', true);
    }

    /**
     * Get all occupant (occupant) history for this unit.
     *
     * @return HasMany
     */
    public function occupants(): HasMany
    {
        return $this->hasMany(Occupant::class);
    }

    /**
     * Get per-unit recurring ledger configurations.
     *
     * @return HasMany
     */
    public function ledgerConfigs(): HasMany
    {
        return $this->hasMany(UnitLedgerConfig::class);
    }

    /**
     * Get active ledger configurations for this unit.
     *
     * @return HasMany
     */
    public function activeLedgerConfigs(): HasMany
    {
        return $this->hasMany(UnitLedgerConfig::class)->where('is_active', true);
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

        if ($this->pq !== null && $this->relationLoaded('community') && $this->community !== null) {
            $totalPq = $this->community->units()->whereNotNull('pq')->sum('pq');
            $budget  = (float) ($this->community->admin_fund_amount ?? 0);
            if ($totalPq > 0 && $budget > 0) {
                return round(($this->pq / $totalPq) * $budget, 2);
            }
        }

        $unitCount = $this->community?->units()->count() ?? 1;

        return $unitCount > 0 ? round(((float) ($this->community?->admin_fund_amount ?? 0)) / $unitCount, 2) : null;
    }

    /**
     * Get the effective reserve levy amount using PQ formula.
     *
     * @return float|null
     */
    public function getEffectiveReserveLevyAttribute(): ?float
    {
        if ($this->pq !== null && $this->relationLoaded('community') && $this->community !== null) {
            $totalPq = $this->community->units()->whereNotNull('pq')->sum('pq');
            $budget  = (float) ($this->community->reserve_fund_amount ?? 0);
            if ($totalPq > 0 && $budget > 0) {
                return round(($this->pq / $totalPq) * $budget, 2);
            }
        }

        $unitCount = $this->community?->units()->count() ?? 1;

        return $unitCount > 0 ? round(((float) ($this->community?->reserve_fund_amount ?? 0)) / $unitCount, 2) : null;
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $query = $this->where($field ?? $this->getRouteKeyName(), $value)
                      ->where('organization_id', $user->organization_id);

        $community = request()->route('community');
        if ($community instanceof Community) {
            $query->where('community_id', $community->id);
        }

        return $query->first();
    }
}
