<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceChecklist extends Model
{
    use HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'financial_year_start' => 'date',
        'financial_year_end'   => 'date',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'financial_year_label',
        'financial_year_start',
        'financial_year_end',
        'notes',
        'organization_id',
        'estate_id',
        'created_by_id',
    ];

    /**
     * Scope a query by search term.
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $query->where('financial_year_label', 'ilike', '%' . $searchTerm . '%')
              ->orWhereHas('estate', function ($q) use ($searchTerm) {
                  $q->where('name', 'ilike', '%' . $searchTerm . '%');
              });
    }

    /**
     * Get the tenant (organisation) this checklist belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the estate this checklist belongs to.
     */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Get the user who created this checklist.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get all checklist items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ComplianceChecklistItem::class)->orderBy('sort_order');
    }

    /**
     * Get completed items only.
     */
    public function completedItems(): HasMany
    {
        return $this->hasMany(ComplianceChecklistItem::class)->where('status', 'completed');
    }

    /**
     * Get overdue items only.
     */
    public function overdueItems(): HasMany
    {
        return $this->hasMany(ComplianceChecklistItem::class)->where('status', 'overdue');
    }
}
