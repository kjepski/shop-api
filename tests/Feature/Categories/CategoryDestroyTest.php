<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_category(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();

        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();

        $this->assertModelMissing($category);
    }

    public function test_category_with_subcategories_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();
        Category::factory()->childOf($category)->create();

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'A category with subcategories cannot be deleted.');

        $this->assertModelExists($category);
    }

    public function test_regular_user_cannot_delete_category(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();

        $this->deleteJson("/api/categories/{$category->id}")->assertForbidden();

        $this->assertModelExists($category);
    }

    public function test_delete_requires_token(): void
    {
        $category = Category::factory()->create();

        $this->deleteJson("/api/categories/{$category->id}")->assertUnauthorized();
    }
}
