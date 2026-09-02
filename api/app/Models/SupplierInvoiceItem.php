<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A single account line on a supplier invoice (GRV): Account · Description · Qty ·
 * Unit Price · Disc · Tax · Total.
 */
class SupplierInvoiceItem extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'account_name' => 'string',
        'description'  => 'string',
        'quantity'     => 'float',
        'unit_price'   => 'float',
        'discount'     => 'float',
        'tax_rate'     => 'float',
        'tax_amount'   => 'float',
        'line_total'   => 'float',
        'sort_order'   => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_name',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
        'supplier_invoice_id',
        'ledger_id',
    ];

    /**
     * Get the parent supplier invoice.
     *
     * @return BelongsTo
     */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    /**
     * Get the account (ledger) this line is posted to.
     *
     * @return BelongsTo
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
