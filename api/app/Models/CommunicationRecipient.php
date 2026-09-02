<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommunicationRecipient extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'recipient_name',
        'recipient_email',
        'status',
        'error',
        'resend_email_id',
        'view_token',
        'communication_id',
    ];

    /**
     * Get the communication this recipient belongs to.
     *
     * @return BelongsTo
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }
}
