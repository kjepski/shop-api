<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_search_matches_name_fragment_case_insensitively(): void
    {
        Product::factory()->create(['name' => 'Garden Rake']);
        Product::factory()->create(['name' => 'Hoe']);

        $this->assertNames($this->getJson('/api/products?search=RAKE'), ['Garden Rake']);
    }

    public function test_search_matches_sku_fragment(): void
    {
        Product::factory()->create(['name' => 'Rake', 'sku' => 'GRD-1001']);
        Product::factory()->create(['name' => 'Hoe', 'sku' => 'TLS-2002']);

        $this->assertNames($this->getJson('/api/products?search=grd-10'), ['Rake']);
    }

    public function test_search_treats_wildcards_literally(): void
    {
        Product::factory()->create(['name' => '100% cotton gloves']);
        Product::factory()->create(['name' => '100 garden gloves']);
        Product::factory()->create(['name' => 'Rake_XL']);
        Product::factory()->create(['name' => 'RakeAXL']);

        $this->assertNames($this->getJson('/api/products?search='.urlencode('0%')), ['100% cotton gloves']);
        $this->assertNames($this->getJson('/api/products?search=e_X'), ['Rake_XL']);
    }

    public function test_search_treats_backslash_literally(): void
    {
        Product::factory()->create(['name' => 'Rake\\Hoe']);
        Product::factory()->create(['name' => 'Rake%Hoe']);

        $this->assertNames($this->getJson('/api/products?search='.urlencode('e\\H')), ['Rake\\Hoe']);
    }

    public function test_category_filter_includes_subcategories(): void
    {
        $garden = Category::factory()->create();
        $tools = Category::factory()->childOf($garden)->create();
        $other = Category::factory()->create();
        Product::factory()->forCategory($garden)->create(['name' => 'In parent']);
        Product::factory()->forCategory($tools)->create(['name' => 'In child']);
        Product::factory()->forCategory($other)->create(['name' => 'Elsewhere']);

        $this->assertNames($this->getJson("/api/products?category_id={$garden->id}"), ['In child', 'In parent']);
    }

    public function test_subcategory_filter_skips_parent_and_sibling_products(): void
    {
        $garden = Category::factory()->create();
        $tools = Category::factory()->childOf($garden)->create();
        $seeds = Category::factory()->childOf($garden)->create();
        Product::factory()->forCategory($garden)->create(['name' => 'In parent']);
        Product::factory()->forCategory($tools)->create(['name' => 'In tools']);
        Product::factory()->forCategory($seeds)->create(['name' => 'In seeds']);

        $this->assertNames($this->getJson("/api/products?category_id={$tools->id}"), ['In tools']);
    }

    public function test_price_range_is_inclusive(): void
    {
        Product::factory()->create(['name' => 'Cheap', 'price' => 999]);
        Product::factory()->create(['name' => 'Lower bound', 'price' => 1000]);
        Product::factory()->create(['name' => 'Upper bound', 'price' => 5000]);
        Product::factory()->create(['name' => 'Pricey', 'price' => 5001]);

        $this->assertNames(
            $this->getJson('/api/products?min_price=1000&max_price=5000'),
            ['Lower bound', 'Upper bound'],
        );
    }

    public function test_single_price_bound_works_on_its_own(): void
    {
        Product::factory()->create(['name' => 'Cheap', 'price' => 999]);
        Product::factory()->create(['name' => 'Pricey', 'price' => 5001]);

        $this->assertNames($this->getJson('/api/products?min_price=1000'), ['Pricey']);
        $this->assertNames($this->getJson('/api/products?max_price=1000'), ['Cheap']);
    }

    public function test_in_stock_filter_skips_sold_out_products(): void
    {
        Product::factory()->create(['name' => 'Available', 'stock' => 1]);
        Product::factory()->create(['name' => 'Sold out', 'stock' => 0]);

        $this->assertNames($this->getJson('/api/products?in_stock=1'), ['Available']);
        $this->assertNames($this->getJson('/api/products?in_stock=0'), ['Available', 'Sold out']);
    }

    public function test_filters_combine(): void
    {
        $category = Category::factory()->create();
        Product::factory()->forCategory($category)->create(['name' => 'Rake match', 'price' => 2000, 'stock' => 5]);
        Product::factory()->forCategory($category)->create(['name' => 'Rake sold out', 'price' => 2000, 'stock' => 0]);
        Product::factory()->forCategory($category)->create(['name' => 'Rake pricey', 'price' => 9000, 'stock' => 5]);
        Product::factory()->create(['name' => 'Rake elsewhere', 'price' => 2000, 'stock' => 5]);
        Product::factory()->forCategory($category)->create(['name' => 'Hoe', 'price' => 2000, 'stock' => 5]);

        $this->assertNames(
            $this->getJson("/api/products?search=rake&category_id={$category->id}&max_price=5000&in_stock=1"),
            ['Rake match'],
        );
    }

    public function test_filters_never_reveal_inactive_products_to_regular_users(): void
    {
        Product::factory()->create(['name' => 'Rake']);
        Product::factory()->inactive()->create(['name' => 'Rake hidden']);
        // Matches only by SKU, so it would leak if the name/SKU OR escaped its group.
        Product::factory()->inactive()->create(['name' => 'Hidden', 'sku' => 'RAKE-00001']);

        $this->assertNames($this->getJson('/api/products?search=rake'), ['Rake']);
    }

    public function test_admin_filters_include_inactive_products(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Product::factory()->create(['name' => 'Rake']);
        Product::factory()->inactive()->create(['name' => 'Rake hidden']);

        $this->assertNames($this->getJson('/api/products?search=rake'), ['Rake', 'Rake hidden']);
    }

    public function test_pagination_links_keep_applied_filters_only(): void
    {
        Product::factory(20)->create(['name' => 'Rake', 'stock' => 3]);

        $response = $this->getJson('/api/products?search=rake&in_stock=1&unknown=x')
            ->assertOk()
            ->assertJsonPath('meta.total', 20);

        $this->assertSame(config('app.url').'/api/products?search=rake&in_stock=1&page=2', $response->json('links.next'));

        $this->getJson('/api/products?search=rake&in_stock=1&page=2')->assertJsonCount(5, 'data');
    }

    public function test_filtered_lists_are_not_cached(): void
    {
        $product = Product::factory()->create(['name' => 'Rake']);

        $this->assertNames($this->getJson('/api/products?search=rak'), ['Rake']);
        $product->updateQuietly(['name' => 'Rake changed']);

        $this->assertNames($this->getJson('/api/products?search=rak'), ['Rake changed']);
    }

    public function test_unknown_query_parameters_do_not_bypass_or_pollute_the_cache(): void
    {
        $product = Product::factory()->create(['name' => 'Rake']);

        $this->getJson('/api/products?unknown=x')->assertOk();
        $product->updateQuietly(['name' => 'Rake changed']);

        $response = $this->getJson('/api/products');
        $this->assertNames($response, ['Rake']);
        $this->assertSame(config('app.url').'/api/products?page=1', $response->json('links.first'));
    }

    public function test_empty_or_negative_filters_use_the_cached_list(): void
    {
        $product = Product::factory()->create(['name' => 'Rake']);

        $this->getJson('/api/products')->assertOk();
        $product->updateQuietly(['name' => 'Rake changed']);

        $this->assertNames($this->getJson('/api/products?in_stock=0'), ['Rake']);
        $this->assertNames($this->getJson('/api/products?search='), ['Rake']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'search too short' => ['search=a', 'search'],
            'search too long' => ['search='.str_repeat('a', 101), 'search'],
            'unknown category' => ['category_id=999999', 'category_id'],
            'category not a number' => ['category_id=abc', 'category_id'],
            'fractional price' => ['min_price=49.99', 'min_price'],
            'negative price' => ['max_price=-1', 'max_price'],
            'price over column limit' => ['min_price=4294967296', 'min_price'],
            'max below min' => ['min_price=5000&max_price=4999', 'max_price'],
            'invalid min only' => ['min_price=abc&max_price=100', 'min_price'],
            'in_stock not boolean' => ['in_stock=maybe', 'in_stock'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(string $query, string $field): void
    {
        $this->getJson("/api/products?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    }

    public function test_filters_require_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/products?search=rake')->assertUnauthorized();
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
