<?php

namespace Tests\Feature\Public;

use App\Models\User;
use App\Models\Place;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function mockSocialite($providerName = 'google', $email = 'testuser@example.com')
    {
        $abstractUser = $this->getMockBuilder('Laravel\Socialite\Two\User')->getMock();
        $abstractUser->method('getName')->willReturn('Test User');
        $abstractUser->method('getEmail')->willReturn($email);
        $abstractUser->method('getAvatar')->willReturn('https://example.com/avatar.jpg');
        $abstractUser->method('getId')->willReturn('123456789');

        $provider = $this->getMockBuilder('Laravel\Socialite\Two\AbstractProvider')->disableOriginalConstructor()->getMock();
        $provider->method('stateless')->willReturn($provider);
        $provider->method('userFromToken')->willReturn($abstractUser);

        Socialite::shouldReceive('driver')->with($providerName)->andReturn($provider);
    }

    public function test_social_authenticate_success_new_user(): void
    {
        $this->mockSocialite('google', 'newuser@example.com');

        $response = $this->postJson('/api/auth/authenticate', [
            'provider' => 'google',
            'token' => 'valid-oauth-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role', 'is_onboarding_completed'],
                'authorization' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'google_id' => '123456789',
            'is_onboarding_completed' => false,
        ]);
    }

    public function test_social_authenticate_success_existing_user(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => '123456789',
        ]);

        $this->mockSocialite('google', 'existing@example.com');

        $response = $this->postJson('/api/auth/authenticate', [
            'provider' => 'google',
            'token' => 'valid-oauth-token',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_social_authenticate_validation_failure(): void
    {
        $response = $this->postJson('/api/auth/authenticate', [
            'provider' => 'invalid-provider',
            'token' => 'token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_social_authenticate_invalid_token_failure(): void
    {
        $provider = $this->getMockBuilder('Laravel\Socialite\Two\AbstractProvider')->disableOriginalConstructor()->getMock();
        $provider->method('stateless')->willReturn($provider);
        $provider->method('userFromToken')->willThrowException(new \Exception('Token is invalid'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->postJson('/api/auth/authenticate', [
            'provider' => 'google',
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error_code' => 'OAUTH_FAILED',
            ]);
    }

    public function test_onboarding_register_success(): void
    {
        $user = User::factory()->create([
            'is_onboarding_completed' => false,
        ]);

        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'valid_username',
                'home' => [
                    'place_name' => 'My Home',
                    'address' => '123 Main St',
                    'lat' => 14.5995,
                    'lng' => 120.9842,
                    'radius' => 150,
                ],
                'safety_preference' => [
                    'sound_recording' => true,
                    'silent_mode' => false,
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'username', 'is_onboarding_completed'],
                'authorization' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $user->refresh();
        $this->assertTrue($user->is_onboarding_completed);
        $this->assertEquals('valid_username', $user->username);

        $this->assertDatabaseHas('places', [
            'user_id' => $user->id,
            'name' => 'My Home',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'key' => 'sound_recording',
            'value' => 'true',
        ]);
    }

    public function test_onboarding_register_conflict_if_already_completed(): void
    {
        $user = User::factory()->create([
            'is_onboarding_completed' => true,
        ]);

        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'new_username',
                'home' => [
                    'place_name' => 'My Home',
                    'address' => '123 Main St',
                    'lat' => 14.5995,
                    'lng' => 120.9842,
                    'radius' => 150,
                ],
                'safety_preference' => [
                    'sound_recording' => true,
                    'silent_mode' => false,
                ]
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'error_code' => 'ONBOARDING_ALREADY_COMPLETED',
            ]);
    }

    public function test_onboarding_register_validation_failures(): void
    {
        $user = User::factory()->create([
            'is_onboarding_completed' => false,
        ]);

        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'username',
                'home.place_name',
                'home.address',
                'home.lat',
                'home.lng',
                'safety_preference.sound_recording',
                'safety_preference.silent_mode',
            ]);
    }

    public function test_onboarding_register_username_validation_failures(): void
    {
        $user = User::factory()->create([
            'is_onboarding_completed' => false,
        ]);
        $token = auth('api')->login($user);

        // 1. Username too short (min:3)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'ab',
                'home' => [
                    'place_name' => 'Home',
                    'address' => '123 Main St',
                    'lat' => 12.34,
                    'lng' => 56.78,
                ],
                'safety_preference' => [
                    'sound_recording' => true,
                    'silent_mode' => false,
                ]
            ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['username']);

        // 2. Username invalid character format (uppercase or symbols)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'Invalid_User!',
                'home' => [
                    'place_name' => 'Home',
                    'address' => '123 Main St',
                    'lat' => 12.34,
                    'lng' => 56.78,
                ],
                'safety_preference' => [
                    'sound_recording' => true,
                    'silent_mode' => false,
                ]
            ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['username']);

        // 3. Username already taken by another user
        User::factory()->create(['username' => 'taken_username']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'taken_username',
                'home' => [
                    'place_name' => 'Home',
                    'address' => '123 Main St',
                    'lat' => 12.34,
                    'lng' => 56.78,
                ],
                'safety_preference' => [
                    'sound_recording' => true,
                    'silent_mode' => false,
                ]
            ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    public function test_onboarding_register_home_coordinates_validation_failures(): void
    {
        $user = User::factory()->create([
            'is_onboarding_completed' => false,
        ]);
        $token = auth('api')->login($user);

        // 1. Latitude out of range (> 90 or < -90)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'valid_username',
                'home' => [
                    'place_name' => 'Home',
                    'address' => '123 Main St',
                    'lat' => 95.0,
                    'lng' => 56.78,
                ],
                'safety_preference' => [
                    'sound_recording' => true,
                    'silent_mode' => false,
                ]
            ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['home.lat']);

        // 2. Longitude out of range (> 180 or < -180)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'valid_username',
                'home' => [
                    'place_name' => 'Home',
                    'address' => '123 Main St',
                    'lat' => 12.34,
                    'lng' => -185.0,
                ],
                'safety_preference' => [
                    'sound_recording' => true,
                    'silent_mode' => false,
                ]
            ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['home.lng']);

        // 3. Radius too small (< 10)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'valid_username',
                'home' => [
                    'place_name' => 'Home',
                    'address' => '123 Main St',
                    'lat' => 12.34,
                    'lng' => 56.78,
                    'radius' => 5,
                ],
                'safety_preference' => [
                    'sound_recording' => true,
                    'silent_mode' => false,
                ]
            ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['home.radius']);
    }

    public function test_onboarding_register_safety_preferences_validation_failures(): void
    {
        $user = User::factory()->create([
            'is_onboarding_completed' => false,
        ]);
        $token = auth('api')->login($user);

        // 1. Safety preferences not boolean
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/register', [
                'username' => 'valid_username',
                'home' => [
                    'place_name' => 'Home',
                    'address' => '123 Main St',
                    'lat' => 12.34,
                    'lng' => 56.78,
                ],
                'safety_preference' => [
                    'sound_recording' => 'not-a-boolean',
                    'silent_mode' => 12345,
                ]
            ]);
        $response->assertStatus(422)->assertJsonValidationErrors([
            'safety_preference.sound_recording',
            'safety_preference.silent_mode'
        ]);
    }

    public function test_logout_success(): void
    {
        $user  = User::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(204);
        $this->assertFalse(auth('api')->check());
    }

    public function test_me_profile_unauthorized_rejection(): void
    {
        $response = $this->getJson('/api/user/me');
        $response->assertStatus(401);
    }

    public function test_me_profile_success(): void
    {
        $user  = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', $user->email);
    }
}
