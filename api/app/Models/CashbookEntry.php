<?php

namespace App\Models;

use App\Enums\CashbookEntryType;
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
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'float',
        'type'   => CashbookEntryType::class,
        'date'   => 'date',
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
        'estate_id',
        'tenant_id',
        'charge_type_id',
        'unit_id',
        'invoice_id',
        'parent_entry_id',
        'proof_of_payment_path',
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
     * Scope to allocated entries only (invoice_id is set).
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function allocated(Builder $query): void
    {
        $query->whereNotNull('invoice_id');
    }

    /**
     * Scope to unallocated entries only (invoice_id is null).
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function unallocated(Builder $query): void
    {
        $query->whereNull('invoice_id');
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
     * Get the estate this cashbook entry belongs to.
     *
     * @return BelongsTo
     */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Get the tenant (organisation) this cashbook entry belongs to.
     *
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the charge type tagged on this entry.
     *
     * @return BelongsTo
     */
    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(ChargeType::class);
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
     * Determine if this entry has been allocated to an invoice.
     *
     * @return bool
     */
    public function getIsAllocatedAttribute(): bool
    {
        return $this->invoice_id !== null;
    }
}
