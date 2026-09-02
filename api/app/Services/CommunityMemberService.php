<?php

namespace App\Services;

use App\Enums\CommunityMemberType;
use App\Http\Resources\CommunityMemberResource;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class CommunityMemberService
{
    /**
     * List a community's members (Settings → Users), optionally filtered.
     * Owners are lazily synced so every owner appears as a User, matching WeConnectU.
     *
     * @param Community   $community
     * @param string|null $filter  all|owners|complex_managers|directors_trustees
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function listMembers(Community $community, ?string $filter = 'all')
    {
        $this->syncOwners($community);

        $query = $community->members();

        switch ($filter) {
            case 'owners':
                $query->where('user_type', CommunityMemberType::OWNER->value);
                break;
            case 'complex_managers':
                $query->where('user_type', CommunityMemberType::COMPLEX_MANAGER->value);
                break;
            case 'directors_trustees':
                $query->where(function ($q) {
                    $q->where('user_type', CommunityMemberType::DIRECTOR_TRUSTEE->value)
                      ->orWhere('is_director_trustee', true);
                });
                break;
        }

        return CommunityMemberResource::collection($query->get());
    }

    /**
     * Ensure every owner of the community exists as a member row (idempotent).
     *
     * @param Community $community
     * @return void
     */
    public function syncOwners(Community $community): void
    {
        $owners = Owner::whereHas('unit', fn ($q) => $q->where('community_id', $community->id))->get();

        $existing = $community->members()->get();

        foreach ($owners as $owner) {
            $name  = trim((string) $owner->full_name);
            $email = $owner->email ? strtolower(trim($owner->email)) : null;

            if ($name === '' && ! $email) {
                continue;
            }

            $match = $existing->first(function (CommunityMember $m) use ($email, $name) {
                if ($email && $m->email) {
                    return strtolower(trim($m->email)) === $email;
                }
                return strtolower(trim($m->name)) === strtolower($name);
            });

            if ($match) {
                // Keep the verified flag in sync with the owner's linked-user state.
                if ((bool) $match->is_verified !== (bool) $owner->user_verified || ! $match->owner_id) {
                    $match->update([
                        'is_verified' => (bool) $owner->user_verified,
                        'owner_id'    => $owner->id,
                    ]);
                }
                continue;
            }

            $member = CommunityMember::create([
                'name'            => $name !== '' ? $name : $email,
                'email'           => $owner->email,
                'cellphone'       => $owner->phone,
                'user_type'       => CommunityMemberType::OWNER->value,
                'is_verified'     => (bool) $owner->user_verified,
                'sort_order'      => ($existing->max('sort_order') ?? 0) + 1,
                'owner_id'        => $owner->id,
                'community_id'    => $community->id,
                'organization_id' => $community->organization_id,
            ]);

            $existing->push($member);
        }
    }

    /**
     * Create a new member.
     *
     * @param Community $community
     * @param array     $data
     * @return array
     */
    public function createMember(Community $community, array $data): array
    {
        $member = CommunityMember::create([
            'name'                => $data['name'],
            'email'               => $data['email'] ?? null,
            'cellphone'           => $data['cellphone'] ?? null,
            'user_type'           => $data['user_type'],
            'is_director_trustee' => (bool) ($data['is_director_trustee'] ?? false),
            'sort_order'          => ($community->members()->max('sort_order') ?? 0) + 1,
            'community_id'        => $community->id,
            'organization_id'     => $community->organization_id,
        ]);

        return ['data' => new CommunityMemberResource($member), 'message' => 'User added.'];
    }

    /**
     * Update a member.
     *
     * @param CommunityMember $member
     * @param array           $data
     * @return array
     */
    public function updateMember(CommunityMember $member, array $data): array
    {
        $member->update(collect($data)->only([
            'name', 'email', 'cellphone', 'user_type', 'is_director_trustee',
        ])->toArray());

        return ['data' => new CommunityMemberResource($member->fresh()), 'message' => 'User updated.'];
    }

    /**
     * Remove a member.
     *
     * @param CommunityMember $member
     * @return array
     */
    public function deleteMember(CommunityMember $member): array
    {
        $member->delete();

        return ['message' => 'User removed.'];
    }

    /**
     * Set which members are directors/trustees (bulk).
     *
     * @param Community $community
     * @param array     $memberIds
     * @return array
     */
    public function updateDirectorsTrustees(Community $community, array $memberIds): array
    {
        $ids = collect($memberIds)->all();

        $community->members()->each(function (CommunityMember $m) use ($ids) {
            $m->update(['is_director_trustee' => in_array($m->id, $ids, true)]);
        });

        return ['message' => 'Directors/Trustees updated.'];
    }

    /**
     * Set which members may authorise payments + the authorisation mode.
     *
     * @param Community $community
     * @param array     $memberIds
     * @param string    $mode
     * @return array
     */
    public function updatePaymentAuthorisations(Community $community, array $memberIds, string $mode): array
    {
        $ids = collect($memberIds)->all();

        $community->members()->each(function (CommunityMember $m) use ($ids) {
            $m->update(['is_payment_authoriser' => in_array($m->id, $ids, true)]);
        });

        $community->update(['payment_authorisation_mode' => $mode]);

        return ['message' => 'Payment authorisations updated.'];
    }

    /**
     * Send a password-reset link to a member's e-mail (if they have one).
     *
     * @param CommunityMember $member
     * @return array
     */
    public function resetPassword(CommunityMember $member): array
    {
        if (! $member->email) {
            return ['message' => 'This user has no e-mail address on file.'];
        }

        $user = User::where('email', $member->email)->first();

        if (! $user) {
            return ['message' => 'No login account exists for this user yet.'];
        }

        if (app()->isLocal() || app()->runningUnitTests()) {
            Log::info('Password reset link requested for community member', ['email' => $member->email]);
        } else {
            Password::sendResetLink(['email' => $member->email]);
        }

        return ['message' => 'Password reset link sent.'];
    }
}
