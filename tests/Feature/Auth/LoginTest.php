<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'is_admin', 'created_at'], 'token', 'expires_at'])
            ->assertJsonPath('data.id', $user->id);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingPath('token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingPath('token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_email_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email' => 'jan@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/login', [
            'email' => '  Jan@Example.COM ',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_is_throttled_per_email_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $payload = ['email' => $user->email, 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/login', $payload)->assertTooManyRequests();

        // A different email from the same IP is not blocked.
        $this->postJson('/api/login', ['email' => 'other@example.com', 'password' => 'x'])
            ->assertUnprocessable();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
