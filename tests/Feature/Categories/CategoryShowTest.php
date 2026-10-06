<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_category(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();

        $this->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.slug', $category->slug);
    }

    public function test_missing_category_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/categories/999')->assertNotFound();
    }

    public function test_view_requires_token(): void
    {
        $category = Category::factory()->create();

        $this->getJson("/api/categories/{$category->id}")->assertUnauthorized();
    }
}
