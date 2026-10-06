<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_category_with_generated_slug(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/categories', ['name' => 'Garden Tools'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Garden Tools')
            ->assertJsonPath('data.slug', 'garden-tools')
            ->assertJsonPath('data.parent_id', null);

        $this->assertDatabaseHas('categories', ['slug' => 'garden-tools', 'parent_id' => null]);
    }

    public function test_admin_can_create_subcategory_with_explicit_slug(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $parent = Category::factory()->create();

        $this->postJson('/api/categories', ['name' => 'Shovels', 'slug' => 'garden-shovels', 'parent_id' => $parent->id])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'garden-shovels')
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_create_requires_name(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/categories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_create_rejects_taken_slug(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Category::factory()->create(['slug' => 'garden-tools']);

        $this->postJson('/api/categories', ['name' => 'Garden Tools'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_rejects_invalid_slug_format(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/categories', ['name' => 'Garden', 'slug' => 'not a slug!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_rejects_missing_parent(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/categories', ['name' => 'Shovels', 'parent_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_create_rejects_third_level_category(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $child = Category::factory()->childOf(Category::factory()->create())->create();

        $this->postJson('/api/categories', ['name' => 'Too Deep', 'parent_id' => $child->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertDatabaseMissing('categories', ['name' => 'Too Deep']);
    }

    public function test_regular_user_cannot_create_category(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/categories', ['name' => 'Garden Tools'])->assertForbidden();

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_create_requires_token(): void
    {
        $this->postJson('/api/categories', ['name' => 'Garden Tools'])->assertUnauthorized();
    }
}
