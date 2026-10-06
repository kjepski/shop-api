<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_active_product_with_category(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.price', $product->price)
            ->assertJsonPath('data.category.id', $product->category_id);
    }

    public function test_inactive_product_is_not_found_for_regular_user(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_admin_can_view_inactive_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_missing_product_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/products/999')->assertNotFound();
    }

    public function test_view_requires_token(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/products/{$product->id}")->assertUnauthorized();
    }
}
