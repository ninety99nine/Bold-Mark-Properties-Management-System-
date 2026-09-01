<?php

namespace App\Models;

use App\Enums\AgeingType;
use App\Enums\CommunityEntityType;
use App\Enums\CommunityStatus;
use App\Enums\PaymentAuthorisationMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Community extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'entity_type'          => CommunityEntityType::class,
        'status'               => CommunityStatus::class,
        'is_active'            => 'boolean',
        'admin_fund_amount'    => 'float',
        'reserve_fund_amount'  => 'float',
        'csos_levy_amount'     => 'float',
        'default_rent_amount'  => 'float',
        'billing_day'             => 'integer',
        'payment_terms_days'      => 'integer',
        'payment_reminder_days'   => 'integer',
        'billing_paused'          => 'boolean',
        'financial_year_end_month' => 'integer',
        'is_vat_registered'        => 'boolean',
        'interest_rate'            => 'float',
        'interest_exempt_threshold' => 'float',
        'ageing_type'              => AgeingType::class,
        'suppress_entity_type'     => 'boolean',
        'apply_pq'                 => 'boolean',
        'transfer_clearance_bank'  => 'array',
        'payment_authorisation_mode' => PaymentAuthorisationMode::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'payment_authorisation_mode',
        'address',
        'postal_address',
        'contact_number',
        'contact_email',
        'entity_type',
        'suppress_entity_type',
        'unit_addressing',
        'apply_pq',
        'interest_period',
        'transfer_clearance_bank',
        'status',
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
        'financial_year_end_month',
        'is_vat_registered',
        'vat_number',
        'interest_rate',
        'interest_exempt_threshold',
        'ageing_type',
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
     * Scope to active communities only.
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
     * Scope by lifecycle status (active | take_on | suspended).
     *
     * @param Builder $query
     * @param string $status
     * @return void
     */
    #[Scope]
    protected function status(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Get the occupant (organisation) this community belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get units within this community.
     *
     * @return HasMany
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get the community's members (Settings → Users list).
     *
     * @return HasMany
     */
    public function members(): HasMany
    {
        return $this->hasMany(CommunityMember::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get active units within this community.
     *
     * @return HasMany
     */
    public function activeUnits(): HasMany
    {
        return $this->hasMany(Unit::class)->where('status', 'active');
    }

    /**
     * Get cashbook entries for this community.
     *
     * @return HasMany
     */
    public function cashbookEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class);
    }

    /**
     * Get bank accounts (current + investment) for this community.
     *
     * @return HasMany
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * Get ledgers enabled for this community.
     *
     * @return BelongsToMany
     */
    public function ledgers(): BelongsToMany
    {
        return $this->belongsToMany(Ledger::class, 'community_ledgers')
                    ->withPivot(['id', 'is_active'])
                    ->using(CommunityLedger::class)
                    ->as('community_ledger')
                    ->withTimestamps();
    }

    /**
     * Get active ledgers enabled for this community.
     *
     * @return BelongsToMany
     */
    public function activeLedgers(): BelongsToMany
    {
        return $this->belongsToMany(Ledger::class, 'community_ledgers')
                    ->withPivot(['id', 'is_active'])
                    ->using(CommunityLedger::class)
                    ->as('community_ledger')
                    ->wherePivot('is_active', true)
                    ->withTimestamps();
    }

    /**
     * Get community ledger junction records.
     *
     * @return HasMany
     */
    public function communityLedgers(): HasMany
    {
        return $this->hasMany(CommunityLedger::class);
    }

    /**
     * Get compliance checklists for this community.
     *
     * @return HasMany
     */
    public function complianceChecklists(): HasMany
    {
        return $this->hasMany(ComplianceChecklist::class);
    }

    /**
     * Get staff users assigned to this community.
     *
     * @return BelongsToMany
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_communities')
                    ->using(UserCommunity::class)
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
