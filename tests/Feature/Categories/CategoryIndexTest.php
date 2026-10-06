<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_categories_sorted_by_name(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $parent = Category::factory()->create(['name' => 'Books']);
        Category::factory()->childOf($parent)->create(['name' => 'Audiobooks']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Audiobooks')
            ->assertJsonPath('data.0.parent_id', $parent->id)
            ->assertJsonPath('data.1.name', 'Books')
            ->assertJsonStructure(['data' => [['id', 'parent_id', 'name', 'slug', 'created_at']]]);
    }

    public function test_list_is_paginated_by_fifteen(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Category::factory(20)->create();

        $this->getJson('/api/categories?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);
    }

    public function test_list_requires_token(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }
}
