<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserResources;

class UserService extends BaseService
{
    /**
     * Return a paginated, filtered list of users for the authenticated tenant.
     *
     * @param array $data
     * @return UserResources
     */
    public function showUsers(array $data): UserResources
    {
        $user  = Auth::user();
        $query = User::where('tenant_id', $user->tenant_id)
            ->with(['roles', 'estates']);

        if (!empty($data['role'])) {
            $query->whereHas('roles', fn($q) => $q->where('name', $data['role']));
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Return aggregate summary statistics for the users page.
     *
     * @param array $data
     * @return array
     */
    public function showUsersSummary(array $data): array
    {
        $user     = Auth::user();
        $tenantId = $user->tenant_id;

        $query = User::where('tenant_id', $tenantId);

        $total    = (clone $query)->count();
        $active   = (clone $query)->where('status', UserStatus::ACTIVE->value)->count();
        $invited  = (clone $query)->where('status', UserStatus::INVITED->value)->count();
        $inactive = (clone $query)->where('status', UserStatus::INACTIVE->value)->count();

        // Internal roles: company-admin, portfolio-manager, financial-controller, portfolio-assistant
        $internalRoles = ['company-admin', 'portfolio-manager', 'financial-controller', 'portfolio-assistant'];
        $internalCount = (clone $query)
            ->whereHas('roles', fn($q) => $q->whereIn('name', $internalRoles))
            ->count();

        $externalCount = $total - $internalCount;

        return [
            'total'          => $total,
            'active'         => $active,
            'invited'        => $invited,
            'inactive'       => $inactive,
            'internal_count' => $internalCount,
            'external_count' => $externalCount,
        ];
    }

    /**
     * Invite a new user to the tenant: create the record and assign their role.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function inviteUser(array $data): array
    {
        $admin = Auth::user();

        $newUser = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make(Str::random(16)),
            'tenant_id' => $admin->tenant_id,
            'status'    => UserStatus::INVITED->value,
        ]);

        $newUser->assignRole($data['role']);

        // Send a dedicated invitation email with role-specific messaging
        try {
            $token       = Password::broker('invitations')->createToken($newUser);
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $setupUrl    = $frontendUrl . '/reset-password?' . http_build_query([
                'token'  => $token,
                'email'  => $newUser->email,
                'broker' => 'invitations',
            ]);

            Mail::send('emails.invite', [
                'name'     => $newUser->name,
                'role'     => $data['role'],
                'setupUrl' => $setupUrl,
            ], function ($message) use ($newUser) {
                $message->to($newUser->email, $newUser->name)
                        ->subject('You\'re invited to BoldMark PMS');
            });
        } catch (\Exception $e) {
            logger()->warning('Failed to send invite email to ' . $newUser->email . ': ' . $e->getMessage());
        }

        return $this->showCreatedResource($newUser);
    }

    /**
     * Bulk delete users by an array of IDs.
     *
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteUsers(array $ids): array
    {
        $admin = Auth::user();

        // Prevent self-deletion
        $ids = array_filter($ids, fn($id) => $id !== $admin->id);

        $users = User::whereIn('id', $ids)
            ->where('tenant_id', $admin->tenant_id)
            ->get();

        $total = $users->count();

        if ($total === 0) {
            throw new Exception('No Users deleted');
        }

        foreach ($users as $user) {
            $user->delete();
        }

        $label = $total === 1 ? 'User' : 'Users';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Return a single user resource with their roles loaded.
     *
     * @param User $user
     * @return UserResource
     */
    public function showUser(User $user): UserResource
    {
        $user->load(['roles', 'estates']);

        return $this->showResource($user);
    }

    /**
     * Sync the estates assigned to a user.
     *
     * @param User  $user
     * @param array $estateIds
     * @return array
     */
    public function syncUserEstates(User $user, array $estateIds): array
    {
        $user->estates()->sync($estateIds);
        $user->load(['roles', 'estates']);

        return $this->showUpdatedResource($user);
    }

    /**
     * Update a user's profile details, role, and/or status.
     *
     * @param User  $user
     * @param array $data
     * @return array
     */
    public function updateUser(User $user, array $data): array
    {
        $profileFields = collect($data)
            ->only(['name', 'email', 'phone', 'status'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        if (!empty($profileFields)) {
            $user->update($profileFields);
        }

        if (!empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        $user->load('roles');

        return $this->showUpdatedResource($user);
    }

    /**
     * Send a password reset link to the given user's email.
     *
     * @param User $user
     * @return array
     */
    public function sendPasswordResetLink(User $user): array
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        return [
            'success' => $status === Password::RESET_LINK_SENT,
            'message' => $status === Password::RESET_LINK_SENT
                ? 'Password reset link sent to ' . $user->email
                : 'Failed to send password reset link. Please try again.',
        ];
    }

    /**
     * Delete a single user.
     *
     * @param User $user
     * @return array
     */
    public function deleteUser(User $user): array
    {
        $deleted = $user->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'User deleted' : 'User delete unsuccessful',
        ];
    }
}
