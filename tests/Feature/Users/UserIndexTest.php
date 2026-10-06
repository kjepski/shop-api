<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_users_sorted_by_name(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(['name' => 'Bob']));
        User::factory()->create(['name' => 'Cecil', 'email' => 'cecil@example.com']);
        User::factory()->create(['name' => 'Alice']);

        $response = $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonPath('data.1.name', 'Bob')
            ->assertJsonPath('data.1.is_admin', true)
            ->assertJsonPath('data.2.email', 'cecil@example.com')
            ->assertJsonPath('data.2.is_admin', false)
            ->assertJsonStructure(['links' => ['first', 'next'], 'meta' => ['current_page', 'last_page', 'total']]);

        $this->assertSame(['id', 'name', 'email', 'is_admin', 'created_at'], array_keys($response->json('data.0')));
    }

    public function test_list_never_exposes_password_or_remember_token(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $content = (string) $this->getJson('/api/users')->assertOk()->getContent();

        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }

    public function test_list_is_paginated_by_fifteen(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory(19)->create();

        $this->getJson('/api/users?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_users_table_has_index_on_name_used_for_sorting(): void
    {
        $indexedColumns = collect(Schema::getIndexes('users'))->pluck('columns');

        $this->assertContains(['name'], $indexedColumns);
    }

    public function test_regular_user_cannot_list_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_list_requires_token(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }
}
