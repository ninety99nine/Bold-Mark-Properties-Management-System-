<?php

namespace App\Models;

use App\Enums\VatType;
use App\Enums\LedgerAppliesTo;
use App\Enums\FinancialCategory;
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
        'is_system'          => 'boolean',
        'is_active'          => 'boolean',
        'is_recurring'       => 'boolean',
        'sort_order'         => 'integer',
        'applies_to'         => LedgerAppliesTo::class,
        'account_type'       => 'string',
        'financial_category' => FinancialCategory::class,
        'tax_type'           => VatType::class,
        'fund'               => 'string',
        'allow_sub_accounts' => 'boolean',
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
        'account_type',
        'financial_category',
        'tax_type',
        'fund',
        'allow_sub_accounts',
        'parent_id',
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
     * Scope to main accounts only (no parent — the X000/000 header rows).
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function main(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Scope to the Reserve Fund parallel chart (RFI/RFE).
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function reserve(Builder $query): void
    {
        $query->where('fund', 'reserve');
    }

    /**
     * Scope to the main (administrative) fund chart.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function mainFund(Builder $query): void
    {
        $query->where('fund', 'main');
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
     * Get the main account this sub-account rolls up to.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the sub-accounts that roll up to this main account.
     *
     * @return HasMany
     */
    public function subAccounts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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

    /**
     * Resolve the single GL control account for an organisation by Financial Category.
     * The GL posting engine uses this to find Customer Control (Accounts Receivable),
     * Supplier Control (Accounts Payable), VAT Control and Retained Income by category
     * rather than by hard-coded code.
     *
     * @param string $organizationId
     * @param FinancialCategory $category
     * @return self|null
     */
    public static function controlAccount(string $organizationId, FinancialCategory $category): ?self
    {
        return self::query()
            ->where('organization_id', $organizationId)
            ->where('financial_category', $category)
            ->first();
    }

    /**
     * The organisation's Suspense / Undefined account (9900/001) — the contra
     * account for unallocated cashbook lines and for invoice / credit-note items
     * that have no income ledger assigned.
     *
     * @param string $organizationId
     * @return self|null
     */
    public static function suspense(string $organizationId): ?self
    {
        return self::query()
            ->where('organization_id', $organizationId)
            ->where('code', '9900/001')
            ->first();
    }

    /**
     * Next free MAIN account code for a prefix, e.g. "8000" => "8000/001".
     * Scans existing "{prefix}/NNN" codes, takes the max numeric suffix + 1, padded to 3.
     *
     * @param string $organizationId
     * @param string $prefix
     * @return string
     */
    public static function nextCode(string $organizationId, string $prefix): string
    {
        $max = self::query()
            ->where('organization_id', $organizationId)
            ->where('code', 'like', $prefix . '/%')
            ->get(['code'])
            ->max(fn (self $ledger): int => (int) substr($ledger->code, strlen($prefix) + 1));

        return $prefix . '/' . str_pad((string) (((int) $max) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Next free SUB-account code under a main code, e.g. "2000/000" => "2000/001".
     * Scans existing "{prefix}/*" codes excluding the "/000" main row, max suffix + 1.
     *
     * @param string $organizationId
     * @param string $mainCode
     * @return string
     */
    public static function nextSubAccountCode(string $organizationId, string $mainCode): string
    {
        $prefix = explode('/', $mainCode)[0];

        $max = self::query()
            ->where('organization_id', $organizationId)
            ->where('code', 'like', $prefix . '/%')
            ->where('code', '!=', $prefix . '/000')
            ->get(['code'])
            ->max(fn (self $ledger): int => (int) substr($ledger->code, strlen($prefix) + 1));

        return $prefix . '/' . str_pad((string) (((int) $max) + 1), 3, '0', STR_PAD_LEFT);
    }
}
