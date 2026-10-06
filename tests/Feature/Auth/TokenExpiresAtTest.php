<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenExpiresAtTest extends TestCase
{
    use RefreshDatabase;

    private function login(): TestResponse
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        return $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-password']);
    }

    private function register(): TestResponse
    {
        return $this->postJson('/api/register', [
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);
    }

    private function storedExpiresAt(TestResponse $response): ?string
    {
        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertNotNull($token);

        return $token->expires_at?->toJSON();
    }

    public function test_login_returns_expires_at_from_config_and_stores_it_on_token(): void
    {
        $this->freezeTime();
        config(['sanctum.expiration' => 60]);

        $response = $this->login();

        $expected = now()->addMinutes(60)->startOfSecond()->toJSON();
        $response->assertOk()->assertJsonPath('expires_at', $expected);
        $this->assertSame($expected, $this->storedExpiresAt($response));
    }

    public function test_register_returns_expires_at_from_config_and_stores_it_on_token(): void
    {
        $this->freezeTime();
        config(['sanctum.expiration' => 60]);

        $response = $this->register();

        $expected = now()->addMinutes(60)->startOfSecond()->toJSON();
        $response->assertCreated()->assertJsonPath('expires_at', $expected);
        $this->assertSame($expected, $this->storedExpiresAt($response));
    }

    public function test_default_expires_at_is_seven_days_ahead(): void
    {
        $this->freezeTime();

        $this->login()->assertJsonPath('expires_at', now()->addDays(7)->startOfSecond()->toJSON());
    }

    public function test_login_expires_at_is_null_when_expiration_is_disabled(): void
    {
        config(['sanctum.expiration' => 0]);

        $response = $this->login();

        $response->assertOk()->assertJsonPath('expires_at', null);
        $this->assertNull($this->storedExpiresAt($response));
    }

    public function test_register_expires_at_is_null_when_expiration_is_disabled(): void
    {
        config(['sanctum.expiration' => 0]);

        $response = $this->register();

        $response->assertCreated()->assertJsonPath('expires_at', null);
        $this->assertNull($this->storedExpiresAt($response));
    }

    public function test_token_works_until_returned_expires_at_and_is_rejected_after(): void
    {
        $response = $this->login();
        $token = $response->json('token');
        $expiresAt = now()->parse($response->json('expires_at'));

        // Disable the global created_at-based check, so only the token's stored expires_at decides.
        config(['sanctum.expiration' => 0]);

        $this->travelTo($expiresAt->copy()->subSecond());
        $this->withToken($token)->getJson('/api/me')->assertOk();

        // The guard caches the resolved user between requests within a single test.
        $this->app['auth']->forgetGuards();

        $this->travelTo($expiresAt->copy()->addSecond());
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }
}
