<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_product_and_category_stays(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertModelMissing($product);
        $this->assertDatabaseHas('categories', ['id' => $product->category_id]);
    }

    public function test_regular_user_cannot_delete_product(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertForbidden();

        $this->assertModelExists($product);
    }

    public function test_inactive_product_is_not_found_for_regular_user_on_delete(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->inactive()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNotFound();

        $this->assertModelExists($product);
    }

    public function test_delete_of_missing_product_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson('/api/products/999')->assertNotFound();
    }

    public function test_delete_requires_token(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertUnauthorized();
    }
}
