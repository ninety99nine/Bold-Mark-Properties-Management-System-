<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NoticeBatchItem extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'balance'     => 'float',
        'charge'      => 'float',
        'emailed'     => 'boolean',
        'credited_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_code',
        'customer_name',
        'from_status',
        'to_status',
        'level',
        'balance',
        'charge',
        'sent_to',
        'customer_type',
        'invoice_id',
        'credited_at',
        'pdf_path',
        'emailed',
        'email',
        'owner_id',
        'notice_batch_id',
        'unit_id',
        'organization_id',
    ];

    /**
     * Get the batch this item belongs to.
     *
     * @return BelongsTo
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(NoticeBatch::class, 'notice_batch_id');
    }

    /**
     * Get the unit this notice was raised against.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the organization this item belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope route-model binding to the authenticated user's organization.
     *
     * @param mixed $value
     * @param string|null $field
     * @return NoticeBatchItem|null
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
