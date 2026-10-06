<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_active_products_sorted_by_name_with_category(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();
        Product::factory()->forCategory($category)->create(['name' => 'Rake', 'price' => 4999]);
        Product::factory()->forCategory($category)->create(['name' => 'Hoe']);
        Product::factory()->inactive()->create(['name' => 'Hidden']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Hoe')
            ->assertJsonPath('data.1.name', 'Rake')
            ->assertJsonPath('data.1.price', 4999)
            ->assertJsonPath('data.1.category.id', $category->id)
            ->assertJsonStructure(['data' => [[
                'id', 'name', 'slug', 'sku', 'description', 'price', 'stock', 'is_active',
                'category' => ['id', 'parent_id', 'name', 'slug'], 'created_at',
            ]]]);
    }

    public function test_admin_sees_inactive_products_too(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Product::factory()->create(['name' => 'Active']);
        Product::factory()->inactive()->create(['name' => 'Hidden']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.name', 'Hidden')
            ->assertJsonPath('data.1.is_active', false);
    }

    public function test_list_is_paginated_by_fifteen_and_counts_only_visible_products(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Product::factory(20)->create();
        Product::factory(3)->inactive()->create();

        $this->getJson('/api/products?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);
    }

    public function test_list_requires_token(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }
}
