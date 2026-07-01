<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateAvatarRequest;
use App\Http\Requests\User\UpdateSettingsRequest;
use App\Http\Requests\User\CheckUsernameRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Dedoc\Scramble\Attributes\Group;

#[Group('User/Profile', weight: 2)]
class ProfileController extends Controller
{
    /**
     * Get profile.
     *
     * Retrieve the authenticated user's profile details.
     *
     * @response UserResource
     */
    public function me(): UserResource
    {
        $user = Auth::guard('api')->user();

        return new UserResource($user);
    }

    /**
     * Update profile.
     *
     * Update the authenticated user's profile information (name and email).
     *
     * @param UpdateProfileRequest $request
     *
     * @see \App\Policies\UserPolicy::update()
     * @response UserResource
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        $this->authorize('update', $user);

        $user->update($request->validated());

        return new UserResource($user);
    }

    /**
     * Update avatar.
     *
     * Upload an avatar file to update the profile.
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar_url' => asset('storage/' . $path)]);
        } else {
            return response()->json([
                'message' => 'No avatar file was provided.'
            ], 400);
        }

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'avatar_url' => $user->avatar_url,
        ]);
    }

    /**
     * Update settings.
     *
     * Update user-specific settings such as location sharing, nearby, etc.
     */
    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
                $user->settings()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $valueStr]
                );
            }
        }

        return response()->json([
            'message' => 'Settings updated successfully.',
            'settings' => $user->settings()->pluck('value', 'key')
        ]);
    }

    /**
     * Check username availability.
     *
     * Check if a username is valid and available (not taken by another user).
     */
    public function checkUsername(CheckUsernameRequest $request): JsonResponse
    {
        $user = $request->user();
        $username = $request->validated('username');

        // If checking their own current username, it's always available
        if ($user && $user->username === $username) {
            return response()->json([
                'available' => true,
            ]);
        }

        // Cache availability globally for 5 minutes (300 seconds)
        $available = \Illuminate\Support\Facades\Cache::remember('username_available_' . $username, 300, function () use ($username) {
            return !\App\Models\User::where('username', $username)->exists();
        });

        return response()->json([
            'available' => $available,
        ]);
    }

    /**
     * Update password.
     *
     * Change the authenticated user's password after validating the current password.
     *
     * @param UpdatePasswordRequest $request
     *
     * @see \App\Policies\UserPolicy::update()
     * @response UserResource
     */
    public function updatePassword(UpdatePasswordRequest $request): UserResource
    {
        $user = $request->user();

        $this->authorize('update', $user);

        $user->update([
            'password' => $request->validated('password')
        ]);

        return new UserResource($user);
    }
}
