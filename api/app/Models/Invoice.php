<?php

namespace App\Models;

use App\Enums\BilledToType;
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
        'billed_to_type' => BilledToType::class,
        'amount'         => 'float',
        'billing_period' => 'date',
        'due_date'       => 'date',
        'sent_at'        => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'invoice_number',
        'status',
        'billed_to_type',
        'billed_to_id',
        'amount',
        'billing_period',
        'due_date',
        'sent_at',
        'issued_by_type',
        'issued_by_user_id',
        'unit_id',
        'charge_type_id',
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
        $query->where('invoice_number', 'like', '%' . $searchTerm . '%');
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
     * Get the charge type for this invoice.
     *
     * @return BelongsTo
     */
    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(ChargeType::class);
    }

    /**
     * Get the tenant (organisation) this invoice belongs to.
     *
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
     * Get the billed-to entity: either an Owner or a UnitTenant.
     * Uses manual resolution since the FK points to two different tables.
     *
     * @return BelongsTo
     */
    public function billedToOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'billed_to_id');
    }

    /**
     * Get the billed-to entity when it is a unit tenant.
     *
     * @return BelongsTo
     */
    public function billedToUnitTenant(): BelongsTo
    {
        return $this->belongsTo(UnitTenant::class, 'billed_to_id');
    }

    /**
     * Get the billed-to person (Owner or UnitTenant) resolved from billed_to_type.
     *
     * @return Owner|UnitTenant|null
     */
    public function getBilledToAttribute(): Owner|UnitTenant|null
    {
        return match ($this->billed_to_type) {
            BilledToType::OWNER  => $this->billedToOwner,
            BilledToType::TENANT => $this->billedToUnitTenant,
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
