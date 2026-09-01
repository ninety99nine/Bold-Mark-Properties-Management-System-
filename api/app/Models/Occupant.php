<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Occupant extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active'        => 'boolean',
        'lease_start'      => 'date',
        'lease_end'        => 'date',
        'move_out_date'    => 'date',
        'secondary_emails' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'full_name',
        'email',
        'secondary_emails',
        'phone',
        'id_number',
        'postal_address',
        'car_registration',
        'user_verified',
        'user_display_name',
        'is_active',
        'lease_start',
        'lease_end',
        'move_out_date',
        'move_out_reason',
        'move_out_notes',
        'lease_document_url',
        'lease_document_name',
        'unit_id',
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
        $query->whereLike('full_name', $searchTerm)
              ->orWhereLike('email', $searchTerm)
              ->orWhereLike('phone', $searchTerm);
    }

    /**
     * Scope to active (current) organizations only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope to past (archived) organizations only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function past(Builder $query): void
    {
        $query->where('is_active', false);
    }

    /**
     * Get the unit this occupant occupies.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the occupant (organisation) this unit occupant belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get invoices billed to this unit occupant.
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'billed_to_id')
                    ->where('billed_to_type', 'occupant');
    }

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
