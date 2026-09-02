<?php

namespace App\Models;

use App\Enums\BilledToType;
use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status'         => InvoiceStatus::class,
        'source'         => InvoiceSource::class,
        'billed_to_type' => BilledToType::class,
        'amount'         => 'float',
        'subtotal'       => 'float',
        'vat_amount'     => 'float',
        'billing_period' => 'date',
        'invoice_date'   => 'date',
        'due_date'       => 'date',
        'sent_at'            => 'datetime',
        'email_failed_at'    => 'datetime',
        'reminder_sent_at'   => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'invoice_number',
        'status',
        'source',
        'billed_to_type',
        'billed_to_id',
        'amount',
        'subtotal',
        'vat_amount',
        'billing_period',
        'invoice_date',
        'due_date',
        'attachment_path',
        'sent_at',
        'email_failed_at',
        'reminder_sent_at',
        'issued_by_type',
        'issued_by_user_id',
        'unit_id',
        'ledger_id',
        'bank_account_id',
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
        // Also match when the user omits dashes/spaces (e.g. "inv 2025 0005" → "INV-2025-0005")
        $normalized = '%' . strtolower(str_replace([' ', '-'], '', $searchTerm)) . '%';

        $query->where(function (Builder $q) use ($searchTerm, $normalized) {
            $q->whereLike('invoice_number', $searchTerm)
              ->orWhereRaw("lower(REPLACE(REPLACE(invoice_number, '-', ''), ' ', '')) like ?", [$normalized]);
        });
    }

    /**
     * Scope to overdue invoices only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::OVERDUE);
    }

    /**
     * Scope to unpaid invoices (sent or overdue).
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function unpaid(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID]);
    }

    /**
     * Scope to invoices for a specific billing period.
     *
     * @param Builder $query
     * @param string $period  e.g. "2026-04-01"
     * @return void
     */
    #[Scope]
    protected function forPeriod(Builder $query, string $period): void
    {
        $query->where('billing_period', $period);
    }

    /**
     * Get the unit this invoice is for.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the ledger for this invoice.
     *
     * @return BelongsTo
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /**
     * Get the bank account nominated for payment of this invoice.
     *
     * @return BelongsTo
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Get the line items for this invoice (WeConnectU-style multi-line customer invoice).
     *
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * Get the occupant (organisation) this invoice belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who issued this invoice (null when issued by the system).
     *
     * @return BelongsTo
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /**
     * Get cashbook entries allocated to this invoice.
     *
     * @return HasMany
     */
    public function cashbookEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class);
    }

    /**
     * Get email tracking events for this invoice.
     *
     * @return HasMany
     */
    public function emailEvents(): HasMany
    {
        return $this->hasMany(InvoiceEmailEvent::class)->orderBy('occurred_at');
    }

    /**
     * Get the billed-to entity: either an Owner or a Occupant.
     * Uses manual resolution since the FK points to two different tables.
     *
     * @return BelongsTo
     */
    public function billedToOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'billed_to_id');
    }

    /**
     * Get the billed-to entity when it is a unit occupant.
     *
     * @return BelongsTo
     */
    public function billedToUnitOccupant(): BelongsTo
    {
        return $this->belongsTo(Occupant::class, 'billed_to_id');
    }

    /**
     * Get the billed-to person (Owner or Occupant) resolved from billed_to_type.
     *
     * @return Owner|Occupant|null
     */
    public function getBilledToAttribute(): Owner|Occupant|null
    {
        return match ($this->billed_to_type) {
            BilledToType::OWNER  => $this->billedToOwner,
            BilledToType::OCCUPANT => $this->billedToUnitOccupant,
            default              => null,
        };
    }

    /**
     * Determine whether this invoice is fully paid.
     *
     * @return bool
     */
    public function getIsPaidAttribute(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    /**
     * Get total amount paid from allocated cashbook entries.
     *
     * @return float
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->cashbookEntries()->sum('amount');
    }

    /**
     * Get outstanding amount on this invoice.
     *
     * @return float
     */
    public function getOutstandingAttribute(): float
    {
        return max(0, $this->amount - $this->total_paid);
    }
}
