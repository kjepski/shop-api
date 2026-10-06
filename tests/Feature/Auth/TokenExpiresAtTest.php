<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenExpiresAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_expires_at_seven_days_ahead_and_stores_it_on_token(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-password']);

        $expected = now()->addDays(7)->startOfSecond();
        $response->assertOk()->assertJsonPath('expires_at', $expected->toJSON());

        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertNotNull($token);
        $this->assertTrue($token->expires_at?->equalTo($expected));
    }

    public function test_register_returns_expires_at_seven_days_ahead_and_stores_it_on_token(): void
    {
        $this->freezeTime();

        $response = $this->postJson('/api/register', [
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $expected = now()->addDays(7)->startOfSecond();
        $response->assertCreated()->assertJsonPath('expires_at', $expected->toJSON());

        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertNotNull($token);
        $this->assertTrue($token->expires_at?->equalTo($expected));
    }

    public function test_expires_at_is_null_when_expiration_is_disabled(): void
    {
        config(['sanctum.expiration' => 0]);
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-password']);

        $response->assertOk()->assertJsonPath('expires_at', null);
        $this->assertNull(PersonalAccessToken::findToken($response->json('token'))?->expires_at);
    }

    public function test_token_is_rejected_after_returned_expires_at(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-password']);

        // Disable the global created_at-based check, so only the token's stored expires_at can reject it.
        config(['sanctum.expiration' => 0]);
        $this->travelTo(now()->parse($response->json('expires_at'))->addSecond());

        $this->withToken($response->json('token'))->getJson('/api/me')->assertUnauthorized();
    }
}
