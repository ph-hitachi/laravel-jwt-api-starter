<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Dedoc\Scramble\Attributes\Group;

#[Group('Admin/Users', weight: 3)]
class UserController extends Controller
{
    /**
     * List users.
     *
     * List all registered users with pagination.
     *
     * @param Request $request
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<UserResource>>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::latest()
            ->cached(300)
            ->paginate(20);

        return UserResource::collection($users);
    }

    /**
     * View user.
     *
     * Retrieve detailed profile information of a specific user.
     *
     * @param User $user
     *
     * @response UserResource
     */
    public function show(User $user): UserResource
    {
        $userData = User::where('id', $user->id)
            ->cached(300)
            ->firstOrFail();

        return new UserResource($userData);
    }

    /**
     * Activate user.
     *
     * Reactivate a deactivated user account, allowing them to login and access the platform.
     *
     * @param User $user
     */
    public function activate(User $user): Response
    {
        $user->update(['is_active' => true]);

        return response()->noContent();
    }

    /**
     * Deactivate user.
     *
     * Deactivate a user account, revoking their active token and session immediately.
     *
     * @param User $user
     */
    public function deactivate(User $user): Response
    {
        $user->update(['is_active' => false]);

        return response()->noContent();
    }

    /**
     * Delete user.
     *
     * Permanently delete a user account from the system database.
     *
     * @param User $user
     */
    public function destroy(User $user): Response
    {
        $user->delete();

        return response()->noContent();
    }

    /**
     * Update user role.
     *
     * Assign a new system role (user, admin) to a user account.
     *
     * @param UpdateRoleRequest $request
     * @param User $user
     */
    public function updateRole(UpdateRoleRequest $request, User $user): Response
    {
        $user->update(['role' => $request->validated('role')]);

        return response()->noContent();
    }
}
