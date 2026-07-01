<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\AuthenticateRequest;
use App\Http\Requests\Auth\RegisterOnboardingRequest;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\AccountDeactivatedException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\OnboardingCompletedException;
use App\Exceptions\ServerErrorException;
use App\Exceptions\OauthException;
use Laravel\Socialite\Facades\Socialite;
use Dedoc\Scramble\Attributes\Group;
use Exception;

#[Group('Authentication', weight: 1)]
class AuthController extends Controller
{
    /**
     * Authenticate user via social provider.
     *
     * Authenticate a user using social oauth provider and token.
     */
    public function authenticate(AuthenticateRequest $request): JsonResponse
    {
        $provider = strtolower($request->input('provider'));
        $token = $request->input('token');

        $name = null;
        $email = null;
        $avatar = null;
        $socialId = null;

        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver($provider);
            $socialUser = $driver->stateless()->userFromToken($token);
            $name = $socialUser->getName() ?? $socialUser->getNickname() ?? 'Social User';
            $email = $socialUser->getEmail();
            $avatar = $socialUser->getAvatar();
            $socialId = $socialUser->getId();
        } catch (Exception $e) {
            throw new OauthException('Social authentication failed. Please try again.');
        }

        if (!$email) {
            throw new OauthException('Social login did not return an email address.');
        }

        $googleId = $provider === 'google' ? $socialId : null;
        $facebookId = $provider === 'facebook' ? $socialId : null;
        $appleId = $provider === 'apple' ? $socialId : null;

        $user = User::where('email', $email)
            ->orWhere('google_id', $googleId)
            ->orWhere('facebook_id', $facebookId)
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'avatar_url' => $avatar,
                'google_id' => $googleId,
                'facebook_id' => $facebookId,
                'is_onboarding_completed' => false,
                'is_active' => true,
            ]);
        } else {
            // Update provider IDs if missing
            $updates = [];
            if ($googleId && !$user->google_id) $updates['google_id'] = $googleId;
            if ($facebookId && !$user->facebook_id) $updates['facebook_id'] = $facebookId;
            if ($avatar && !$user->avatar_url) $updates['avatar_url'] = $avatar;
            if (!empty($updates)) {
                $user->update($updates);
            }
        }

        if (!$user->is_active) {
            throw new AccountDeactivatedException();
        }

        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');
        $jwtToken = $guard->login($user);

        return $this->respondWithToken($jwtToken, $user);
    }

    /**
     * Register / Complete Onboarding.
     *
     * Complete onboarding for the currently authenticated user by adding username, home place, and safety preferences.
     */
    public function register(RegisterOnboardingRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        if ($user->is_onboarding_completed) {
            throw new OnboardingCompletedException();
        }

        DB::beginTransaction();

        try {
            $user->update([
                'username' => $request->input('username'),
                'is_onboarding_completed' => true,
            ]);

            // Save Home Place
            $home = $request->input('home');
            $user->places()->create([
                'name' => $home['place_name'],
                'address' => $home['address'],
                'latitude' => $home['lat'],
                'longitude' => $home['lng'],
                'radius' => $home['radius'] ?? 150,
                'icon' => 'house',
                'geofence_type' => 'circle',
            ]);

            // Save Safety Preferences
            $safety = $request->input('safety_preference');
            $user->settings()->updateOrCreate(
                ['key' => 'sound_recording'],
                ['value' => json_encode($safety['sound_recording'])]
            );
            $user->settings()->updateOrCreate(
                ['key' => 'silent_mode'],
                ['value' => json_encode($safety['silent_mode'])]
            );

            DB::commit();

            /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $token = $guard->login($user);

            return $this->respondWithToken($token, $user->fresh());

        } catch (Exception $e) {
            DB::rollBack();
            throw new ServerErrorException('Failed to complete onboarding. Please try again later.');
        }
    }

    /**
     * Authenticate user.
     *
     * Authenticate a user using their email and password credentials to receive a stateless JWT access token.
     *
     * @param LoginRequest $request
     *
     * @response array{user: UserResource, authorization: array{access_token: string, token_type: string, expires_in: int}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');

        if (!$token = $guard->attempt($credentials)) {
            throw new InvalidCredentialsException();
        }

        /** @var User $user */
        $user = $guard->user();

        if (!$user->is_active) {
            $guard->logout();
            throw new AccountDeactivatedException();
        }

        return $this->respondWithToken($token, $user);
    }

    /**
     * Logout user.
     *
     * Revoke the user's current JWT access token and log them out of the application.
     */
    public function logout(): Response
    {
        Auth::guard('api')->logout();

        return response()->noContent();
    }

    /**
     * Refresh token.
     *
     * Refresh the user's current authentication token, invalidating the old one and returning a new JWT.
     *
     * @response array{user: UserResource, authorization: array{access_token: string, token_type: string, expires_in: int}}
     */
    public function refresh(): JsonResponse
    {
        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->refresh();
        $user = $guard->user();

        return $this->respondWithToken($token, $user);
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(string $token, User $user): JsonResponse
    {
        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');

        return response()->json([
            'user' => new UserResource($user),
            'authorization' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ]);
    }
}
