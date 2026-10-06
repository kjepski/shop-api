<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_rename_category_without_changing_slug(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create(['name' => 'Garden', 'slug' => 'garden']);

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'Garden & Yard'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Garden & Yard')
            ->assertJsonPath('data.slug', 'garden');
    }

    public function test_admin_can_keep_own_slug_and_move_category_under_parent(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $parent = Category::factory()->create();
        $category = Category::factory()->create(['slug' => 'shovels']);

        $this->patchJson("/api/categories/{$category->id}", ['slug' => 'shovels', 'parent_id' => $parent->id])
            ->assertOk()
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_admin_can_move_subcategory_to_top_level(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->childOf(Category::factory()->create())->create();

        $this->patchJson("/api/categories/{$category->id}", ['parent_id' => null])
            ->assertOk()
            ->assertJsonPath('data.parent_id', null);
    }

    public function test_update_rejects_slug_taken_by_another_category(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Category::factory()->create(['slug' => 'taken']);
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}", ['slug' => 'taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_rejects_empty_name(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}", ['parent_id' => $category->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_category_with_children_cannot_get_a_parent(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();
        Category::factory()->childOf($category)->create();
        $otherRoot = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}", ['parent_id' => $otherRoot->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertNull($category->fresh()?->parent_id);
    }

    public function test_category_cannot_be_moved_under_a_subcategory(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $child = Category::factory()->childOf(Category::factory()->create())->create();
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}", ['parent_id' => $child->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_regular_user_cannot_update_category(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create(['name' => 'Garden']);

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'Hacked'])->assertForbidden();

        $this->assertSame('Garden', $category->fresh()?->name);
    }

    public function test_update_requires_token(): void
    {
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'X'])->assertUnauthorized();
    }

    public function test_update_of_missing_category_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson('/api/categories/999', ['name' => 'X'])->assertNotFound();
    }
}
