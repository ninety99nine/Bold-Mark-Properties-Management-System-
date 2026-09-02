<?php

namespace App\Models;

use App\Enums\CashbookEntryType;
use App\Enums\JournalLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashbookEntry extends Model
{
    use HasFactory, HasUuids;

    /**
     * Remove this entry's GL batch whenever it is deleted, so the bank ledger no
     * longer carries a movement for a line that no longer exists. Child (split)
     * rows never post their own batch, so there is nothing to remove for them.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::deleted(function (CashbookEntry $entry): void {
            app(\App\Services\GeneralLedgerPostingService::class)->deleteBatchesFor($entry);
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'float',
        'type'   => CashbookEntryType::class,
        'date'   => 'date',
        'allocated_at' => 'datetime',
        'allocation_ledger_type' => JournalLineType::class,
        'is_split' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'description',
        'amount',
        'type',
        'date',
        'notes',
        'community_id',
        'organization_id',
        'ledger_id',
        'unit_id',
        'supplier_id',
        'invoice_id',
        'bank_account_id',
        'parent_entry_id',
        'allocation_ledger_type',
        'is_split',
        'vat_type',
        'allocation_remarks',
        'proof_of_payment_path',
        'allocated_by_name',
        'allocated_at',
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
        $query->where('description', 'like', '%' . $searchTerm . '%')
              ->orWhere('notes', 'like', '%' . $searchTerm . '%');
    }

    /**
     * Scope to allocated entries only (a ledger type has been assigned or the
     * line has been split into child allocations).
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function allocated(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereNotNull('allocation_ledger_type')
                  ->orWhere('is_split', true);
        });
    }

    /**
     * Scope to unallocated entries only (no ledger type assigned and not split).
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function unallocated(Builder $query): void
    {
        $query->whereNull('allocation_ledger_type')
              ->where('is_split', false);
    }

    /**
     * Scope to credit entries only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function credits(Builder $query): void
    {
        $query->where('type', CashbookEntryType::CREDIT);
    }

    /**
     * Scope to debit entries only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function debits(Builder $query): void
    {
        $query->where('type', CashbookEntryType::DEBIT);
    }

    /**
     * Get the community this cashbook entry belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the occupant (organisation) this cashbook entry belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the ledger tagged on this entry.
     *
     * @return BelongsTo
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /**
     * Get the unit this entry is allocated to.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the invoice this entry is allocated against.
     *
     * @return BelongsTo
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the supplier this entry is allocated to.
     *
     * @return BelongsTo
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the bank account (cashbook) this entry belongs to.
     *
     * @return BelongsTo
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Get the parent entry (if this entry was created by splitting a larger payment).
     *
     * @return BelongsTo
     */
    public function parentEntry(): BelongsTo
    {
        return $this->belongsTo(CashbookEntry::class, 'parent_entry_id');
    }

    /**
     * Get child entries created from splitting this entry.
     *
     * @return HasMany
     */
    public function childEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class, 'parent_entry_id');
    }

    /**
     * Determine if this entry has been allocated (a ledger type is assigned or
     * the line has been split into child allocations).
     *
     * @return bool
     */
    public function getIsAllocatedAttribute(): bool
    {
        return $this->allocation_ledger_type !== null || $this->is_split;
    }
}
