<?php

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierInvoiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A supplier invoice ("GRV" — Goods Received Voucher), the WeConnectU "SUPPLIER
 * TAX INVOICE". Raised against a supplier for a community; posts a CREDIT to the
 * supplier ledger (increases what the community owes), aged by invoice_date.
 */
class SupplierInvoice extends Model
{
    use HasFactory, HasUuids;

    /**
     * Reverse this supplier invoice's GL batch whenever it is deleted, so the
     * Accounts-Payable accrual and the supplier balance follow the document.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::deleted(function (SupplierInvoice $supplierInvoice): void {
            app(\App\Services\GeneralLedgerPostingService::class)->deleteBatchesFor($supplierInvoice);
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'grv_number'             => 'string',
        'status'                 => SupplierInvoiceStatus::class,
        'type'                   => SupplierInvoiceType::class,
        'source_document_number' => 'string',
        'supplier_reference'     => 'string',
        'our_reference'          => 'string',
        'description'            => 'string',
        'attachments'            => 'array',
        'invoice_date'           => 'date',
        'due_date'               => 'date',
        'financial_year'         => 'integer',
        'subtotal'               => 'float',
        'discount'               => 'float',
        'vat_amount'             => 'float',
        'total'                  => 'float',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'grv_number',
        'status',
        'type',
        'source_document_number',
        'supplier_reference',
        'our_reference',
        'description',
        'attachments',
        'invoice_date',
        'due_date',
        'financial_year',
        'subtotal',
        'discount',
        'vat_amount',
        'total',
        'supplier_id',
        'community_id',
        'organization_id',
        'created_by_user_id',
    ];

    /**
     * Scope a query by search term (GRV number / references / description).
     *
     * @param Builder $query
     * @param string $searchTerm
     * @return void
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $query->where(function (Builder $query) use ($searchTerm) {
            $query->whereLike('grv_number', $searchTerm)
                  ->orWhereLike('supplier_reference', $searchTerm)
                  ->orWhereLike('our_reference', $searchTerm)
                  ->orWhereLike('description', $searchTerm);
        });
    }

    /**
     * Get the supplier this invoice is raised against.
     *
     * @return BelongsTo
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the community this invoice belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the organization this invoice belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created this invoice.
     *
     * @return BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the line items on this invoice.
     *
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * Resolve route binding scoped to the authenticated user's organization.
     *
     * @param mixed $value
     * @param string|null $field
     * @return self|null
     */
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
