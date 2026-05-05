<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceChecklist extends Model
{
    use HasFactory, HasUuids;

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
        $query->whereLike('financial_year_label', $searchTerm)
              ->orWhereHas('estate', function ($q) use ($searchTerm) {
                  $q->whereLike('name', $searchTerm);
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
     * Overdue = explicitly marked 'overdue' OR pending/in_progress with a past due_date.
     */
    public function overdueItems(): HasMany
    {
        return $this->hasMany(ComplianceChecklistItem::class)
            ->where(function ($q) {
                $q->where('status', 'overdue')
                  ->orWhere(function ($inner) {
                      $inner->whereIn('status', ['pending', 'in_progress'])
                            ->whereNotNull('due_date')
                            ->whereDate('due_date', '<', now()->toDateString());
                  });
            });
    }

    /**
     * Get waived items only.
     */
    public function waivedItems(): HasMany
    {
        return $this->hasMany(ComplianceChecklistItem::class)->where('status', 'waived');
    }

    /**
     * The earliest incomplete, non-waived item — shown as "Up Next".
     */
    public function nextItem(): HasOne
    {
        return $this->hasOne(ComplianceChecklistItem::class)
            ->whereNotIn('status', ['completed', 'waived'])
            ->orderByRaw("CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, sort_order ASC");
    }
}
