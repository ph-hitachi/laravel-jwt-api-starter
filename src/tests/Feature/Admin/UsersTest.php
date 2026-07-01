<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->token = auth('api')->login($this->admin);

        $this->user = User::factory()->create(['role' => 'user']);
    }

    public function test_admin_can_list_all_users(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'is_active']
                ],
                'links',
                'meta' => ['current_page']
            ]);
    }

    public function test_admin_can_view_user_details(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/admin/users/{$this->user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('user.email', $this->user->email);
    }

    public function test_admin_can_deactivate_user_and_revoke_tokens(): void
    {
        // Pre-generate a JWT for the user (simulating they are logged in)
        $userToken = auth('api')->login($this->user);
        // Re-login as admin so the guard context is reset
        $this->token = auth('api')->login($this->admin);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/admin/users/{$this->user->id}/deactivate");

        $response->assertStatus(204);

        // Verify deactivated in DB
        $this->assertFalse((bool) $this->user->fresh()->is_active);

        // Verify user cannot use their token after deactivation
        // (EnsureUserIsActive middleware blocks them)
        auth('api')->logout();
        $responseAuthCheck = $this->withHeader('Authorization', "Bearer {$userToken}")
            ->getJson('/api/user/me');
        $responseAuthCheck->assertStatus(403);
    }

    public function test_admin_can_activate_user(): void
    {
        $this->user->update(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/admin/users/{$this->user->id}/activate");

        $response->assertStatus(204);
        $this->assertTrue((bool) $this->user->fresh()->is_active);
    }

    public function test_admin_can_delete_user_without_active_orders(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/admin/users/{$this->user->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    public function test_admin_can_update_user_role(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/admin/users/{$this->user->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertStatus(204);
        $this->assertEquals('admin', $this->user->fresh()->role);
    }
}
