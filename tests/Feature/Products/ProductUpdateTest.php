<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_price_stock_and_category(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['slug' => 'rake']);
        $newCategory = Category::factory()->create();

        $this->patchJson("/api/products/{$product->id}", [
            'price' => 1250,
            'stock' => 3,
            'category_id' => $newCategory->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.price', 1250)
            ->assertJsonPath('data.stock', 3)
            ->assertJsonPath('data.slug', 'rake')
            ->assertJsonPath('data.category.id', $newCategory->id);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 1250, 'category_id' => $newCategory->id]);
    }

    public function test_admin_can_deactivate_product_and_clear_description(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create();

        $this->patchJson("/api/products/{$product->id}", ['is_active' => false, 'description' => null])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.description', null);
    }

    public function test_admin_can_keep_own_slug_and_sku(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['slug' => 'rake', 'sku' => 'RAKE-001']);

        $this->patchJson("/api/products/{$product->id}", ['slug' => 'rake', 'sku' => 'rake-001'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'rake')
            ->assertJsonPath('data.sku', 'RAKE-001');
    }

    public function test_update_rejects_slug_and_sku_of_another_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Product::factory()->create(['slug' => 'taken', 'sku' => 'TAKEN-1']);
        $product = Product::factory()->create();

        $this->patchJson("/api/products/{$product->id}", ['slug' => 'taken', 'sku' => 'taken-1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'sku']);
    }

    public function test_update_rejects_invalid_values(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['price' => 1000]);

        $this->patchJson("/api/products/{$product->id}", [
            'price' => 10.5,
            'stock' => -1,
            'name' => '',
            'category_id' => 999,
            'is_active' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'stock', 'name', 'category_id', 'is_active']);

        $this->assertSame(1000, $product->fresh()?->price);
    }

    public function test_regular_user_gets_403_before_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->patchJson("/api/products/{$product->id}", ['price' => -1])->assertForbidden();
    }

    public function test_regular_user_cannot_update_product(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['price' => 1000]);

        $this->patchJson("/api/products/{$product->id}", ['price' => 1])->assertForbidden();

        $this->assertSame(1000, $product->fresh()?->price);
    }

    public function test_inactive_product_is_not_found_for_regular_user_on_update(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->inactive()->create();

        $this->patchJson("/api/products/{$product->id}", ['price' => 1])->assertNotFound();
    }

    public function test_update_requires_token(): void
    {
        $product = Product::factory()->create();

        $this->patchJson("/api/products/{$product->id}", ['price' => 1])->assertUnauthorized();
    }

    public function test_update_of_missing_product_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson('/api/products/999', ['price' => 1])->assertNotFound();
    }
}
