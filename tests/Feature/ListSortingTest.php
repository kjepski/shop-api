<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListSortingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function productSorts(): array
    {
        return [
            'empty sort means default' => ['', ['Apple', 'Melon', 'Zucchini']],
            'name' => ['name', ['Apple', 'Melon', 'Zucchini']],
            '-name' => ['-name', ['Zucchini', 'Melon', 'Apple']],
            'price' => ['price', ['Melon', 'Zucchini', 'Apple']],
            '-price' => ['-price', ['Apple', 'Zucchini', 'Melon']],
            'created_at' => ['created_at', ['Zucchini', 'Apple', 'Melon']],
            '-created_at' => ['-created_at', ['Melon', 'Apple', 'Zucchini']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('productSorts')]
    public function test_products_can_be_sorted(string $sort, array $expected): void
    {
        Sanctum::actingAs(User::factory()->create());
        Product::factory()->create(['name' => 'Zucchini', 'price' => 500, 'created_at' => now()->subDays(3)]);
        Product::factory()->create(['name' => 'Apple', 'price' => 900, 'created_at' => now()->subDays(2)]);
        Product::factory()->create(['name' => 'Melon', 'price' => 100, 'created_at' => now()->subDay()]);

        $this->assertNames($this->getJson("/api/products?sort={$sort}"), $expected);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function userSorts(): array
    {
        return [
            'empty sort means default' => ['', ['Ala', 'Ewa', 'Ola']],
            'name' => ['name', ['Ala', 'Ewa', 'Ola']],
            '-name' => ['-name', ['Ola', 'Ewa', 'Ala']],
            'email' => ['email', ['Ewa', 'Ola', 'Ala']],
            '-email' => ['-email', ['Ala', 'Ola', 'Ewa']],
            'created_at' => ['created_at', ['Ola', 'Ala', 'Ewa']],
            '-created_at' => ['-created_at', ['Ewa', 'Ala', 'Ola']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('userSorts')]
    public function test_users_can_be_sorted(string $sort, array $expected): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ola', 'email' => 'b@example.com', 'created_at' => now()->subDays(3)]);
        User::factory()->create(['name' => 'Ala', 'email' => 'c@example.com', 'created_at' => now()->subDays(2)]);
        User::factory()->create(['name' => 'Ewa', 'email' => 'a@example.com', 'created_at' => now()->subDay()]);
        Sanctum::actingAs($admin);

        $this->assertNames($this->getJson("/api/users?sort={$sort}"), $expected);
    }

    public function test_ties_are_broken_by_id_in_the_same_direction(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $first = Product::factory()->create(['price' => 100]);
        $second = Product::factory()->create(['price' => 100]);

        $this->assertSame([$first->id, $second->id], array_column($this->getJson('/api/products?sort=price')->json('data'), 'id'));
        $this->assertSame([$second->id, $first->id], array_column($this->getJson('/api/products?sort=-price')->json('data'), 'id'));
    }

    public function test_pagination_over_ties_never_repeats_or_skips_a_row(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Product::factory(20)->create(['price' => 100]);

        $ids = [
            ...array_column($this->getJson('/api/products?sort=-price')->json('data'), 'id'),
            ...array_column($this->getJson('/api/products?sort=-price&page=2')->json('data'), 'id'),
        ];

        $this->assertCount(20, array_unique($ids));
    }

    public function test_sort_combines_with_filters_and_stays_in_pagination_links(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Product::factory(16)->create(['name' => 'Rake', 'price' => 100]);
        Product::factory()->create(['name' => 'Rake gold', 'price' => 9999]);
        Product::factory()->create(['name' => 'Hoe', 'price' => 99999]);

        $response = $this->getJson('/api/products?search=rake&sort=-price')
            ->assertOk()
            ->assertJsonPath('meta.total', 17)
            ->assertJsonPath('data.0.name', 'Rake gold');

        $this->assertSame(config('app.url').'/api/products?search=rake&sort=-price&page=2', $response->json('links.next'));
    }

    public function test_default_sort_is_left_out_of_pagination_links(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Product::factory(16)->create();
        User::factory(16)->create();

        $this->assertSame(config('app.url').'/api/products?page=2', $this->getJson('/api/products?sort=name')->json('links.next'));
        $this->assertStringEndsWith('/api/users?page=2', (string) $this->getJson('/api/users?sort=name')->json('links.next'));
        $this->assertStringEndsWith('/api/users?role=user&sort=-email&page=2', (string) $this->getJson('/api/users?role=user&sort=-email')->json('links.next'));
    }

    public function test_each_sort_of_the_plain_product_list_is_cached_separately(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $cheap = Product::factory()->create(['name' => 'Cheap', 'price' => 100]);
        Product::factory()->create(['name' => 'Pricey', 'price' => 900]);

        $this->assertNames($this->getJson('/api/products?sort=price'), ['Cheap', 'Pricey']);
        $cheap->updateQuietly(['name' => 'Renamed']);

        // Same sort: still the cached page.
        $this->assertNames($this->getJson('/api/products?sort=price'), ['Cheap', 'Pricey']);
        // Another sort: its own entry, built fresh in its own order.
        $this->assertNames($this->getJson('/api/products?sort=-price'), ['Pricey', 'Renamed']);
    }

    public function test_default_sort_shares_the_cache_entry_with_no_sort(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Rake']);

        $this->getJson('/api/products')->assertOk();
        $product->updateQuietly(['name' => 'Hoe']);

        $this->assertNames($this->getJson('/api/products?sort=name'), ['Rake']);
    }

    public function test_product_change_flushes_every_cached_sort(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['name' => 'Rake', 'price' => 100]);

        $this->getJson('/api/products?sort=price')->assertOk();
        $this->getJson('/api/products?sort=-created_at')->assertOk();

        $this->patchJson("/api/products/{$product->id}", ['name' => 'Hoe'])->assertOk();

        $this->assertNames($this->getJson('/api/products?sort=price'), ['Hoe']);
        $this->assertNames($this->getJson('/api/products?sort=-created_at'), ['Hoe']);
    }

    public function test_sort_alone_does_not_use_up_the_search_limit(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        for ($i = 0; $i < 61; $i++) {
            $this->getJson('/api/products?sort=-price')->assertOk();
            $this->getJson('/api/users?sort=-email')->assertOk();
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidSorts(): array
    {
        return [
            'unknown column on products' => ['/api/products?sort=stock'],
            'column from another model' => ['/api/products?sort=email'],
            'wrong case' => ['/api/products?sort=Price'],
            'sql in sort' => ['/api/products?sort='.urlencode('price;drop table products')],
            'double minus' => ['/api/products?sort=--price'],
            'minus alone' => ['/api/products?sort=-'],
            'id' => ['/api/products?sort=id'],
            'several columns' => ['/api/products?sort=price,name'],
            'array' => ['/api/products?sort[]=price'],
            'unknown column on users' => ['/api/users?sort=password'],
            'is_admin on users' => ['/api/users?sort=is_admin'],
        ];
    }

    #[DataProvider('invalidSorts')]
    public function test_invalid_sort_is_rejected(string $uri): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson($uri)->assertUnprocessable()->assertJsonValidationErrors(['sort']);
    }

    public function test_sorted_scope_refuses_columns_outside_the_whitelist(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Product::query()->sorted('stock')->get();
    }

    public function test_sort_indexes_exist(): void
    {
        $products = collect(Schema::getIndexes('products'))->pluck('columns');
        $users = collect(Schema::getIndexes('users'))->pluck('columns');

        foreach ([['price'], ['created_at'], ['is_active', 'price'], ['is_active', 'created_at']] as $columns) {
            $this->assertContains($columns, $products);
        }
        $this->assertContains(['created_at'], $users);
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertNames(TestResponse $response, array $expected): void
    {
        $response->assertOk();

        $this->assertSame($expected, array_column($response->json('data'), 'name'));
    }
}
