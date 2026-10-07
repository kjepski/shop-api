<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('api');
        $otherToken = $user->createToken('other-device');

        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);

        // The guard caches the resolved user between requests within a single test.
        $this->app['auth']->forgetGuards();

        $this->withToken($currentToken->plainTextToken)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_logout_from_the_panel_domain_still_revokes_the_token(): void
    {
        // The old /admin panel sends a Bearer token from the same domain as the cookie-based panel.
        config(['sanctum.stateful' => ['localhost']]);
        $token = User::factory()->create()->createToken('api');

        $this->withHeader('Referer', 'http://localhost/admin')
            ->withToken($token->plainTextToken)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_logout_requires_token(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
