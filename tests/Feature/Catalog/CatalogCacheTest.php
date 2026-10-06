<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Data changed directly in the database (bypassing Actions) stays invisible until
 * the cache is flushed, which is how these tests tell a cached response from a fresh one.
 */
class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_list_is_served_from_cache(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Rake']);

        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Rake');
        Product::query()->whereKey($product->id)->update(['name' => 'Changed']);

        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.name', 'Rake');
    }

    public function test_category_list_is_served_from_cache(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create(['name' => 'Garden']);

        $this->getJson('/api/categories')->assertJsonPath('data.0.name', 'Garden');
        Category::query()->whereKey($category->id)->update(['name' => 'Changed']);

        $this->getJson('/api/categories')->assertOk()->assertJsonPath('data.0.name', 'Garden');
    }

    public function test_cached_list_expires_after_configured_ttl(): void
    {
        config(['cache.catalog_ttl' => 60]);
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Rake']);

        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Rake');
        Product::query()->whereKey($product->id)->update(['name' => 'Changed']);

        $this->travel(59)->seconds();
        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Rake');

        $this->travel(2)->seconds();
        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Changed');
    }

    public function test_creating_product_refreshes_product_list(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create();

        $this->getJson('/api/products')->assertJsonCount(0, 'data');

        $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Rake',
            'slug' => 'rake',
            'sku' => 'RAKE-1',
            'price' => 4999,
        ])->assertCreated();

        $this->getJson('/api/products')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Rake');
    }

    public function test_updating_product_refreshes_product_list(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['name' => 'Rake']);

        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Rake');
        $this->patchJson("/api/products/{$product->id}", ['name' => 'Hoe'])->assertOk();

        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Hoe');
    }

    public function test_deactivating_product_hides_it_from_cached_public_list(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($user);
        $this->getJson('/api/products')->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson("/api/products/{$product->id}", ['is_active' => false])->assertOk();

        Sanctum::actingAs($user);
        $this->getJson('/api/products')->assertJsonCount(0, 'data');
    }

    public function test_deleting_product_refreshes_product_list(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create();

        $this->getJson('/api/products')->assertJsonCount(1, 'data');
        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->getJson('/api/products')->assertJsonCount(0, 'data');
    }

    public function test_creating_category_refreshes_category_list(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/categories')->assertJsonCount(0, 'data');
        $this->postJson('/api/categories', ['name' => 'Garden', 'slug' => 'garden'])->assertCreated();

        $this->getJson('/api/categories')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Garden');
    }

    public function test_deleting_category_refreshes_category_list(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();

        $this->getJson('/api/categories')->assertJsonCount(1, 'data');
        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();

        $this->getJson('/api/categories')->assertJsonCount(0, 'data');
    }

    public function test_renaming_category_refreshes_category_and_product_lists(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create(['name' => 'Garden']);
        Product::factory()->forCategory($category)->create();

        $this->getJson('/api/categories')->assertJsonPath('data.0.name', 'Garden');
        $this->getJson('/api/products')->assertJsonPath('data.0.category.name', 'Garden');

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'Yard'])->assertOk();

        $this->getJson('/api/categories')->assertJsonPath('data.0.name', 'Yard');
        $this->getJson('/api/products')->assertJsonPath('data.0.category.name', 'Yard');
    }

    public function test_admin_cached_list_does_not_leak_inactive_products_to_users(): void
    {
        Product::factory()->create(['name' => 'Active']);
        Product::factory()->inactive()->create(['name' => 'Hidden']);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/products')->assertJsonCount(2, 'data');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/products')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active');
    }

    public function test_user_cached_list_does_not_hide_inactive_products_from_admins(): void
    {
        Product::factory()->create();
        Product::factory()->inactive()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/products')->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/products')->assertJsonCount(2, 'data');
    }

    public function test_each_page_is_cached_separately(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Product::factory(20)->create();

        $this->getJson('/api/products')
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1);

        $this->getJson('/api/products?page=2')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_invalid_page_shares_cache_entry_with_first_page(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Rake']);

        $this->getJson('/api/products?page=abc')->assertJsonPath('meta.current_page', 1);
        Product::query()->whereKey($product->id)->update(['name' => 'Changed']);

        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Rake');
    }

    public function test_pages_past_the_last_one_are_not_cached(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Product::factory(15)->create();

        $this->getJson('/api/products?page=2')->assertJsonCount(0, 'data');
        Product::factory()->create();

        $this->getJson('/api/products?page=2')->assertJsonCount(1, 'data');
    }

    public function test_pagination_links_do_not_come_from_request_host(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Product::factory(20)->create();

        $this->getJson('http://evil.example/api/products')->assertOk();

        $response = $this->getJson('/api/products')->assertOk();
        $this->assertSame(config('app.url').'/api/products?page=2', $response->json('links.next'));
        $this->assertStringNotContainsString('evil.example', $response->getContent() ?: '');
    }

    public function test_updating_product_refreshes_admin_list_and_later_pages(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        Product::factory(15)->create(['name' => 'Aaa']);
        $product = Product::factory()->inactive()->create(['name' => 'Zzz']);

        $this->getJson('/api/products?page=2')->assertJsonPath('data.0.name', 'Zzz');
        $this->patchJson("/api/products/{$product->id}", ['name' => 'Zzz Renamed'])->assertOk();

        $this->getJson('/api/products?page=2')->assertJsonPath('data.0.name', 'Zzz Renamed');
    }

    public function test_product_change_keeps_category_list_cached(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create(['name' => 'Garden']);
        $product = Product::factory()->forCategory($category)->create();

        $this->getJson('/api/categories')->assertJsonPath('data.0.name', 'Garden');
        Category::query()->whereKey($category->id)->update(['name' => 'Changed']);
        $this->patchJson("/api/products/{$product->id}", ['name' => 'Hoe'])->assertOk();

        $this->getJson('/api/categories')->assertJsonPath('data.0.name', 'Garden');
    }

    public function test_stale_cache_tags_are_pruned_hourly_on_one_server(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains((string) $event->command, 'cache:prune-stale-tags'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->onOneServer);
    }
}
