<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];
    }

    public function test_user_can_register_and_receives_working_token(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at'], 'token'])
            ->assertJsonPath('data.email', 'jan@example.com')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'jan@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret-password', $user->password));

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_register_requires_all_fields(): void
    {
        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_invalid_email(): void
    {
        $this->postJson('/api/register', [...$this->validPayload(), 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_taken_email(): void
    {
        User::factory()->create(['email' => 'jan@example.com']);

        $this->postJson('/api/register', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_rejects_unconfirmed_password(): void
    {
        $this->postJson('/api/register', [...$this->validPayload(), 'password_confirmation' => 'different'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_too_short_password(): void
    {
        $this->postJson('/api/register', [...$this->validPayload(), 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_too_long_name(): void
    {
        $this->postJson('/api/register', [...$this->validPayload(), 'name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_register_normalizes_email_and_rejects_case_variant_duplicate(): void
    {
        $this->postJson('/api/register', [...$this->validPayload(), 'email' => ' Jan@Example.COM '])
            ->assertCreated()
            ->assertJsonPath('data.email', 'jan@example.com');

        $this->postJson('/api/register', [...$this->validPayload(), 'email' => 'JAN@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_is_throttled_after_six_attempts(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
        }

        $this->postJson('/api/register', [])->assertTooManyRequests();
    }
}
