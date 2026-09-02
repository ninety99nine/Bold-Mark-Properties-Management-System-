<?php

namespace App\Models;

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var array
     */
    protected $casts = [
        'line_type'  => JournalLineType::class,
        'entry_type' => JournalEntryType::class,
        'amount'     => 'float',
        'sort_order' => 'integer',
        'due_date'   => 'date',
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'line_type',
        'description',
        'due_date',
        'amount',
        'entry_type',
        'sort_order',
        'journal_batch_id',
        'organization_id',
        'ledger_id',
        'unit_id',
        'supplier_id',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
