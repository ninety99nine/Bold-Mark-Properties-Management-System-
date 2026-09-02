<?php

namespace App\Models;

use App\Enums\CommunityMemberType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommunityMember extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'user_type'             => CommunityMemberType::class,
        'is_director_trustee'   => 'boolean',
        'is_payment_authoriser' => 'boolean',
        'is_verified'           => 'boolean',
        'sort_order'            => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'cellphone',
        'user_type',
        'is_director_trustee',
        'is_payment_authoriser',
        'is_verified',
        'sort_order',
        'user_id',
        'owner_id',
        'community_id',
        'organization_id',
    ];

    /**
     * Derived "Type" column label, matching WeConnectU:
     * Owner · Owner/Trustee · Director/Trustee · Complex Manager.
     *
     * @return string
     */
    public function getTypeLabelAttribute(): string
    {
        if ($this->user_type === CommunityMemberType::DIRECTOR_TRUSTEE) {
            return 'Director/Trustee';
        }

        if ($this->user_type === CommunityMemberType::COMPLEX_MANAGER) {
            return 'Complex Manager';
        }

        // Owner (+ trustee flag)
        return $this->is_director_trustee ? 'Owner/Trustee' : 'Owner';
    }

    /**
     * Whether this member counts as a director/trustee (base type or flag).
     *
     * @return bool
     */
    public function isDirectorTrustee(): bool
    {
        return $this->user_type === CommunityMemberType::DIRECTOR_TRUSTEE || $this->is_director_trustee;
    }

    /**
     * Get the community this member belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the organization this member belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
