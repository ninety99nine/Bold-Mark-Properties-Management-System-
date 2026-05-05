<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceItemAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'compliance_checklist_item_id',
        'uploaded_by_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ComplianceChecklistItem::class, 'compliance_checklist_item_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
