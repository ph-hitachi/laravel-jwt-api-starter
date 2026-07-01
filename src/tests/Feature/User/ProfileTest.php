<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_successfully(): void
    {
        $user = User::factory()->create([
            'name'  => 'Old Name',
            'email' => 'old@example.com',
            'role'  => 'user',
        ]);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/user/profile', [
                'name'            => 'New Name',
                'username'        => 'new_username',
                'phone_number'    => '+639123456789',
                'phone_iso_code'  => 'PH',
                'phone_dial_code' => '+63',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'New Name')
            ->assertJsonPath('user.username', 'new_username')
            ->assertJsonPath('user.phone_number', '+639123456789')
            ->assertJsonPath('user.email', 'old@example.com');

        $this->assertDatabaseHas('users', [
            'id'              => $user->id,
            'name'            => 'New Name',
            'username'        => 'new_username',
            'phone_number'    => '+639123456789',
            'phone_iso_code'  => 'PH',
            'phone_dial_code' => '+63',
            'email'           => 'old@example.com',
        ]);
    }

    public function test_user_cannot_update_profile_with_taken_username(): void
    {
        User::factory()->create([
            'username' => 'taken_username',
        ]);

        $user = User::factory()->create([
            'name'  => 'Standard User',
            'role'  => 'user',
        ]);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/user/profile', [
                'name'     => 'Standard User',
                'username' => 'taken_username',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_user_cannot_update_profile_role(): void
    {
        $user = User::factory()->create([
            'name'  => 'Standard User',
            'email' => 'user@example.com',
            'role'  => 'user',
        ]);
        $token = auth('api')->login($user);

        // Attempting to change role to admin
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/user/profile', [
                'name'  => 'Standard User',
                'email' => 'user@example.com',
                'role'  => 'admin', // Trying to change role to admin
            ]);

        $response->assertStatus(200);

        // Verify that user's role remains 'user' in database
        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'role' => 'user',
        ]);

        $this->assertDatabaseMissing('users', [
            'id'   => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_user_cannot_update_avatar_via_url_directly(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/user/avatar', [
                'avatar_url' => 'https://example.com/custom-avatar.png'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_user_can_upload_avatar_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        $file = \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/user/avatar', [
                'avatar' => $file
            ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertNotNull($user->avatar_url);
        $this->assertStringContainsString('avatars/', $user->avatar_url);

        $filename = basename($user->avatar_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('avatars/' . $filename);
    }

    public function test_user_can_update_settings_successfully(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/user/settings', [
                'location_sharing' => true,
                'nearby' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'settings' => [
                    'location_sharing' => 'true',
                    'nearby' => 'false',
                ]
            ]);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'key' => 'location_sharing',
            'value' => 'true',
        ]);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'key' => 'nearby',
            'value' => 'false',
        ]);
    }

    public function test_user_cannot_update_settings_with_unwhitelisted_keys(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/user/settings', [
                'location_sharing' => true,
                'unallowed_settings_key' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unallowed_settings_key']);
    }

    public function test_user_can_check_username_availability_success(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        // 1. Available username
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/username?username=my_available_username');

        $response->assertStatus(200)
            ->assertExactJson([
                'available' => true,
            ]);

        // 2. Taken username (by another user)
        User::factory()->create(['username' => 'another_user_username']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/username?username=another_user_username');

        $response->assertStatus(200)
            ->assertExactJson([
                'available' => false,
            ]);

        // 3. Current user's own username (should be considered available)
        $user->update(['username' => 'my_own_username']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/username?username=my_own_username');

        $response->assertStatus(200)
            ->assertExactJson([
                'available' => true,
            ]);

        // 4. Test cache invalidation: check 'cache_test_username' (returns true and gets cached)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/username?username=cache_test_username');
        $response->assertStatus(200)
            ->assertExactJson([
                'available' => true,
            ]);

        // Create a user with 'cache_test_username' to trigger model event and clear cache
        User::factory()->create(['username' => 'cache_test_username']);

        // Check again: should bypass cache (which was cleared) and query database, returning false
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/username?username=cache_test_username');
        $response->assertStatus(200)
            ->assertExactJson([
                'available' => false,
            ]);
    }

    public function test_user_check_username_validation_failures(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        // 1. Missing username parameter
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/username');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);

        // 2. Too short
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/username?username=ab');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);

        // 3. Invalid characters
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/username?username=invalid-format!');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    // public function test_user_can_update_password_successfully(): void
    // {
    //     $user = User::factory()->create([
    //         'role'     => 'user',
    //         'password' => 'OldPassword123!',
    //     ]);
    //     $token = auth('api')->login($user);
    // 
    //     $response = $this->withHeader('Authorization', "Bearer {$token}")
    //         ->putJson('/api/user/password', [
    //             'current_password'      => 'OldPassword123!',
    //             'password'              => 'NewPassword123!',
    //             'password_confirmation' => 'NewPassword123!',
    //         ]);
    // 
    //     $response->assertStatus(200);
    // 
    //     // Verify user password hash has been updated
    //     $user->refresh();
    //     $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword123!', $user->password));
    // }
}
