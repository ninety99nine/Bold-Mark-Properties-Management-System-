<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserResources;
use App\Http\Requests\User\ShowUsersRequest;
use App\Http\Requests\User\ShowUsersSummaryRequest;
use App\Http\Requests\User\InviteUserRequest;
use App\Http\Requests\User\ShowUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\DeleteUserRequest;
use App\Http\Requests\User\DeleteUsersRequest;
use App\Http\Requests\User\SendPasswordResetRequest;
use App\Http\Requests\User\SyncUserEstatesRequest;

class UserController extends Controller
{
    protected UserService $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of users for the authenticated tenant.
     *
     * @param ShowUsersRequest $request
     * @return UserResources
     */
    public function showUsers(ShowUsersRequest $request): UserResources
    {
        return $this->service->showUsers($request->validated());
    }

    /**
     * Return aggregate user summary statistics.
     *
     * @param ShowUsersSummaryRequest $request
     * @return array
     */
    public function showUsersSummary(ShowUsersSummaryRequest $request): array
    {
        return $this->service->showUsersSummary($request->validated());
    }

    /**
     * Invite a new user: creates the record and assigns their role.
     *
     * @param InviteUserRequest $request
     * @return array
     */
    public function inviteUser(InviteUserRequest $request): array
    {
        return $this->service->inviteUser($request->validated());
    }

    /**
     * Bulk delete users.
     *
     * @param DeleteUsersRequest $request
     * @return array
     */
    public function deleteUsers(DeleteUsersRequest $request): array
    {
        return $this->service->deleteUsers($request->input('user_ids', []));
    }

    /**
     * Return a single user.
     *
     * @param ShowUserRequest $request
     * @param User            $user
     * @return UserResource
     */
    public function showUser(ShowUserRequest $request, User $user): UserResource
    {
        return $this->service->showUser($user);
    }

    /**
     * Update a user's profile details.
     *
     * @param UpdateUserRequest $request
     * @param User              $user
     * @return array
     */
    public function updateUser(UpdateUserRequest $request, User $user): array
    {
        return $this->service->updateUser($user, $request->validated());
    }

    /**
     * Delete a single user.
     *
     * @param DeleteUserRequest $request
     * @param User              $user
     * @return array
     */
    public function deleteUser(DeleteUserRequest $request, User $user): array
    {
        return $this->service->deleteUser($user);
    }

    /**
     * Send a password reset link to the given user.
     *
     * @param SendPasswordResetRequest $request
     * @param User                     $user
     * @return array
     */
    public function sendPasswordResetLink(SendPasswordResetRequest $request, User $user): array
    {
        return $this->service->sendPasswordResetLink($user);
    }

    /**
     * Sync the estates assigned to a user.
     *
     * @param SyncUserEstatesRequest $request
     * @param User                   $user
     * @return array
     */
    public function syncUserEstates(SyncUserEstatesRequest $request, User $user): array
    {
        return $this->service->syncUserEstates($user, $request->input('estate_ids', []));
    }
}
