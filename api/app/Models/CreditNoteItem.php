<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    use HasFactory, HasUuids;

    protected $casts = [
        'quantity'   => 'float',
        'amount'     => 'float',
        'tax_rate'   => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'description',
        'quantity',
        'amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
        'credit_note_id',
        'ledger_id',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
