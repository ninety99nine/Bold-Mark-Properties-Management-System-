<?php

namespace App\Models;

use App\Enums\ComplianceItemPriority;
use App\Enums\ComplianceItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceChecklistItem extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status'       => ComplianceItemStatus::class,
        'priority'     => ComplianceItemPriority::class,
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'is_recurring' => 'boolean',
        'sort_order'   => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'category',
        'description',
        'status',
        'priority',
        'due_date',
        'sort_order',
        'is_recurring',
        'completion_notes',
        'evidence_file_path',
        'evidence_file_name',
        'completed_at',
        'compliance_checklist_id',
        'organization_id',
        'assigned_to_id',
        'completed_by_id',
    ];

    /**
     * Scope a query by search term.
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $query->whereLike('name', $searchTerm)
              ->orWhereLike('category', $searchTerm);
    }

    /**
     * Scope to items with a specific status.
     */
    #[Scope]
    protected function withStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Scope to items in a specific category.
     */
    #[Scope]
    protected function inCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }

    /**
     * Get the checklist this item belongs to.
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ComplianceChecklist::class, 'compliance_checklist_id');
    }

    /**
     * Get the occupant (organisation).
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user this item is assigned to.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * Get the user who completed this item.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * Get the attachments for this item.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ComplianceItemAttachment::class, 'compliance_checklist_item_id');
    }
}
