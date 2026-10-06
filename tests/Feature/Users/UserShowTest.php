<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_user(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->create(['name' => 'Jan', 'email' => 'jan@example.com']);

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['id', 'name', 'email', 'is_admin', 'created_at']])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'jan@example.com')
            ->assertJsonPath('data.is_admin', false);
    }

    public function test_regular_user_cannot_view_other_user(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $other = User::factory()->create();

        $this->getJson("/api/users/{$other->id}")->assertForbidden();
    }

    public function test_regular_user_cannot_view_self_through_admin_endpoint(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/users/{$user->id}")->assertForbidden();
    }

    public function test_missing_user_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/users/999999')->assertNotFound();
    }

    public function test_show_requires_token(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }
}
