<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_user_together_with_tokens(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();
        $user->createToken('phone');
        $user->createToken('laptop');
        $bystander = User::factory()->create();
        $bystander->createToken('api');

        $this->deleteJson("/api/users/{$user->id}")->assertNoContent();

        $this->assertModelMissing($user);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $bystander->id]);
    }

    public function test_deleted_users_token_stops_working(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->deleteJson("/api/users/{$user->id}")->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertModelExists($admin);
    }

    public function test_admin_can_delete_another_admin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $other = User::factory()->admin()->create();

        $this->deleteJson("/api/users/{$other->id}")->assertNoContent();

        $this->assertModelMissing($other);
    }

    public function test_regular_user_cannot_delete_user(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $other = User::factory()->create();

        $this->deleteJson("/api/users/{$other->id}")->assertForbidden();

        $this->assertModelExists($other);
    }

    public function test_delete_of_missing_user_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson('/api/users/999999')->assertNotFound();
    }

    public function test_delete_requires_token(): void
    {
        $user = User::factory()->create();

        $this->deleteJson("/api/users/{$user->id}")->assertUnauthorized();
    }
}
