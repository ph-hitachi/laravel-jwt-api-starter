<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

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
                'user' => ['id', 'name', 'email', 'role'],
                'authorization' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'google_id' => '123456789',
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

    public function test_register_success(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'authorization' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function test_register_validation_failures(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);
    }

    public function test_login_success(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'authorization' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);
    }

    public function test_login_invalid_credentials_failure(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error_code' => 'INVALID_CREDENTIALS',
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
