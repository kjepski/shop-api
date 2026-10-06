<?php

namespace Tests\Feature;

use App\Http\Requests\Products\IndexProductRequest;
use App\Http\Requests\Users\IndexUserRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtered_product_lists_are_limited_to_sixty_per_minute(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->hitTimes(60, '/api/products?search=rake');

        $retryAfter = (int) $this->getJson('/api/products?search=rake')
            ->assertTooManyRequests()
            ->headers->get('Retry-After');

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function test_in_stock_zero_counts_as_filter_input(): void
    {
        // Known trade-off: the limiter only checks that a filter parameter is present.
        Sanctum::actingAs(User::factory()->create());

        $this->hitTimes(60, '/api/products?in_stock=0');

        $this->getJson('/api/products?in_stock=0')->assertTooManyRequests();
    }

    public function test_every_filter_counts_towards_the_limit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();

        $this->hitTimes(15, "/api/products?category_id={$category->id}");
        $this->hitTimes(15, '/api/products?min_price=100');
        $this->hitTimes(15, '/api/products?max_price=100');
        $this->hitTimes(15, '/api/products?in_stock=1');

        $this->getJson('/api/products?search=rake')->assertTooManyRequests();
    }

    public function test_invalid_searches_count_too(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->hitTimes(60, '/api/products?search=a', 422);

        $this->getJson('/api/products?search=rake')->assertTooManyRequests();
    }

    public function test_plain_list_is_not_limited(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->hitTimes(70, '/api/products');
        $this->hitTimes(10, '/api/products?search=');
    }

    public function test_plain_list_still_works_after_search_limit_is_reached(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->hitTimes(60, '/api/products?search=rake');

        $this->getJson('/api/products?search=rake')->assertTooManyRequests();
        $this->getJson('/api/products')->assertOk();
    }

    public function test_limit_is_counted_per_user(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->hitTimes(60, '/api/products?search=rake');
        $this->getJson('/api/products?search=rake')->assertTooManyRequests();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/products?search=rake')->assertOk();
    }

    public function test_limit_resets_after_a_minute(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->hitTimes(60, '/api/products?search=rake');
        $this->getJson('/api/products?search=rake')->assertTooManyRequests();

        $this->travel(61)->seconds();

        $this->getJson('/api/products?search=rake')->assertOk();
    }

    public function test_user_search_is_limited_too(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->hitTimes(30, '/api/users?search=jan');
        $this->hitTimes(30, '/api/users?role=admin');

        $this->getJson('/api/users?search=jan')->assertTooManyRequests();
        $this->getJson('/api/users')->assertOk();
    }

    public function test_products_and_users_have_separate_budgets(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->hitTimes(60, '/api/products?search=rake');
        $this->getJson('/api/products?search=rake')->assertTooManyRequests();

        $this->getJson('/api/users?search=jan')->assertOk();
    }

    public function test_filter_lists_match_validation_rules(): void
    {
        // A filter missing from FILTERS would silently skip the rate limit.
        $this->assertEqualsCanonicalizing(array_keys((new IndexProductRequest)->rules()), IndexProductRequest::FILTERS);
        $this->assertEqualsCanonicalizing(array_keys((new IndexUserRequest)->rules()), IndexUserRequest::FILTERS);
    }

    public function test_unauthenticated_requests_get_401_not_429(): void
    {
        $this->hitTimes(70, '/api/products?search=rake', 401);
    }

    private function hitTimes(int $times, string $uri, int $status = 200): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->getJson($uri)->assertStatus($status);
        }
    }
}
