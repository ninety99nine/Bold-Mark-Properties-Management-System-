<?php

namespace App\Models;

use App\Enums\JournalSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalBatch extends Model
{
    use HasFactory, HasUuids;

    /**
     * The fixed set of Journal Group tags, matching WeConnectU exactly.
     *
     * @var array<string>
     */
    public const GROUPS = [
        'Accrual',
        'Audit',
        'Customer Recovery',
        'Insurance',
        'Interest on Arrears',
        'Legal Fees',
        'Levy',
        'Opening Balances',
        'Petty Cash',
        'Transfer',
        'Water and Sewerage',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'date'           => 'date',
        'financial_year' => 'integer',
        'batch_number'   => 'integer',
        'files'          => 'array',
        'source'         => JournalSource::class,
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'batch_number',
        'journal_group',
        'date',
        'financial_year',
        'files',
        'source',
        'source_type',
        'source_id',
        'community_id',
        'organization_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * The WeConnectU-style batch name shown in the list ("Journal Batch 38").
     * Auto-postings have no batch number, so they fall back to their source label.
     *
     * @return string
     */
    public function getBatchNameAttribute(): string
    {
        if ($this->batch_number) {
            return 'Journal Batch ' . $this->batch_number;
        }

        return $this->source instanceof JournalSource ? $this->source->label() : 'Journal';
    }

    /**
     * Scope to manually-created batches only (the Journals page list).
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function manual(Builder $query): void
    {
        $query->where('source', JournalSource::MANUAL->value);
    }

    /**
     * Search by batch name / number.
     *
     * @param Builder $query
     * @param string  $searchTerm
     * @return void
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $digits = preg_replace('/\D/', '', $searchTerm);

        $query->where(function (Builder $q) use ($searchTerm, $digits) {
            $q->whereLike('journal_group', $searchTerm);

            if ($digits !== '') {
                $q->orWhere('batch_number', (int) $digits);
            }
        });
    }

    /**
     * The originating document (invoice, credit note, cashbook entry, supplier
     * invoice) for an auto-posted batch. Null for manual journals. Named
     * `sourceDocument` to avoid colliding with the `source` enum column.
     *
     * @return MorphTo
     */
    public function sourceDocument(): MorphTo
    {
        return $this->morphTo('sourceDocument', 'source_type', 'source_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
