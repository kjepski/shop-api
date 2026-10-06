<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        User::factory()->create();

        $this->withToken($user->createToken('api')->plainTextToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['id', 'name', 'email', 'is_admin', 'created_at']])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.is_admin', false);
    }

    public function test_me_tells_admin_apart(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withToken($admin->createToken('api')->plainTextToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.is_admin', true);
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_rejects_invalid_token(): void
    {
        $this->withToken('1|invalid-token')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
