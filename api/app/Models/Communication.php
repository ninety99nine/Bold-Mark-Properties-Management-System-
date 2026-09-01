<?php

namespace App\Models;

use App\Enums\CommunicationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Communication extends Model
{
    use HasFactory, HasUuids;

    /**
     * Scope a query by search term (subject, sender, recipient email).
     *
     * @param Builder $query
     * @param string $searchTerm
     * @return void
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $query->where(function (Builder $query) use ($searchTerm) {
            $query->whereLike('subject', "%{$searchTerm}%")
                  ->orWhereLike('from_email', "%{$searchTerm}%")
                  ->orWhereHas('recipients', fn (Builder $r) => $r->whereLike('recipient_email', "%{$searchTerm}%"));
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type'             => CommunicationType::class,
        'recipient_groups' => 'array',
        'attachment_names' => 'array',
        'recipient_count'  => 'integer',
        'sent_count'       => 'integer',
        'error_count'      => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'from_name',
        'from_email',
        'subject',
        'body',
        'bcc',
        'type',
        'status',
        'sent_by_name',
        'recipient_count',
        'sent_count',
        'error_count',
        'recipient_groups',
        'attachment_names',
        'community_id',
        'sent_by_user_id',
        'organization_id',
    ];

    /**
     * Get the community this communication was sent to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the organization this communication belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who sent this communication.
     *
     * @return BelongsTo
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Get the individual recipient delivery rows for this communication.
     *
     * @return HasMany
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }
}
