<?php

namespace App\Models;

use App\Enums\BilledToType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $casts = [
        'billed_to_type'   => BilledToType::class,
        'amount'           => 'float',
        'subtotal'         => 'float',
        'vat_amount'       => 'float',
        'credit_note_date' => 'date',
        'sent_at'          => 'datetime',
    ];

    protected $fillable = [
        'credit_note_number',
        'billed_to_type',
        'billed_to_id',
        'reason',
        'order_no',
        'reference',
        'amount',
        'subtotal',
        'vat_amount',
        'credit_note_date',
        'sent_at',
        'issued_by_type',
        'issued_by_user_id',
        'unit_id',
        'applied_invoice_id',
        'organization_id',
    ];

    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $normalized = '%' . strtolower(str_replace([' ', '-'], '', $searchTerm)) . '%';
        $query->where(function (Builder $q) use ($searchTerm, $normalized) {
            $q->whereLike('credit_note_number', $searchTerm)
              ->orWhereRaw("lower(REPLACE(REPLACE(credit_note_number, '-', ''), ' ', '')) like ?", [$normalized]);
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function appliedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'applied_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class)->orderBy('sort_order');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function billedToOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'billed_to_id');
    }

    public function billedToUnitOccupant(): BelongsTo
    {
        return $this->belongsTo(Occupant::class, 'billed_to_id');
    }

    public function getBilledToAttribute(): Owner|Occupant|null
    {
        return match ($this->billed_to_type) {
            BilledToType::OWNER    => $this->billedToOwner,
            BilledToType::OCCUPANT => $this->billedToUnitOccupant,
            default                => null,
        };
    }
}
