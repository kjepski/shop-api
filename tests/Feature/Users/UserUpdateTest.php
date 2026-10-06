<?php

namespace Tests\Feature\Users;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_name_and_email(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();

        $this->patchJson("/api/users/{$user->id}", ['name' => 'Jan Nowak', 'email' => '  Jan.Nowak@Example.com '])
            ->assertOk()
            ->assertJsonPath('data.name', 'Jan Nowak')
            ->assertJsonPath('data.email', 'jan.nowak@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Jan Nowak', 'email' => 'jan.nowak@example.com']);
    }

    public function test_admin_can_keep_users_own_email(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->create(['email' => 'jan@example.com']);

        $this->patchJson("/api/users/{$user->id}", ['email' => 'jan@example.com'])->assertOk();
    }

    public function test_promoted_user_gets_admin_rights_immediately(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson("/api/users/{$user->id}", ['is_admin' => true])
            ->assertOk()
            ->assertJsonPath('data.is_admin', true);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/users')->assertOk();
    }

    public function test_demoted_admin_loses_rights_immediately(): void
    {
        $other = User::factory()->admin()->create();
        $token = $other->createToken('api')->plainTextToken;

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson("/api/users/{$other->id}", ['is_admin' => false])
            ->assertOk()
            ->assertJsonPath('data.is_admin', false);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/products', Product::factory()->raw())->assertForbidden();
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$admin->id}", ['is_admin' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_admin' => 'You cannot remove your own admin role.']);

        $this->assertTrue($admin->fresh()?->is_admin);
    }

    public function test_admin_can_edit_own_name_and_keep_role(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$admin->id}", ['name' => 'Boss', 'is_admin' => true])
            ->assertOk()
            ->assertJsonPath('data.name', 'Boss')
            ->assertJsonPath('data.is_admin', true);
    }

    public function test_update_cannot_change_password(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();
        $hash = $user->password;

        $this->patchJson("/api/users/{$user->id}", ['name' => 'Jan', 'password' => 'new-password-123'])->assertOk();

        $this->assertSame($hash, $user->fresh()?->password);
    }

    public function test_update_rejects_email_of_another_user(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->patchJson("/api/users/{$user->id}", ['email' => 'TAKEN@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidFields(): array
    {
        return [
            'empty name' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'email too long' => [['email' => str_repeat('a', 250).'@example.com'], 'email'],
            'is_admin as string' => [['is_admin' => '1'], 'is_admin'],
            'is_admin as integer' => [['is_admin' => 1], 'is_admin'],
            'is_admin null' => [['is_admin' => null], 'is_admin'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidFields')]
    public function test_update_rejects_invalid_values(array $payload, string $field): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();

        $this->patchJson("/api/users/{$user->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    }

    public function test_regular_user_gets_403_before_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $other = User::factory()->create();

        $this->patchJson("/api/users/{$other->id}", ['email' => 'not-an-email'])->assertForbidden();
    }

    public function test_regular_user_cannot_promote_self(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/users/{$user->id}", ['is_admin' => true])->assertForbidden();

        $this->assertFalse($user->fresh()?->is_admin);
    }

    public function test_update_of_missing_user_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson('/api/users/999999', ['name' => 'Jan'])->assertNotFound();
    }

    public function test_update_requires_token(): void
    {
        $user = User::factory()->create();

        $this->patchJson("/api/users/{$user->id}", ['name' => 'Jan'])->assertUnauthorized();
    }
}
