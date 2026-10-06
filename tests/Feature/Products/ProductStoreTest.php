<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'name' => 'Garden Rake',
            'sku' => 'RAKE-001',
            'price' => 4999,
        ];
    }

    public function test_admin_can_create_product_with_defaults_and_generated_slug(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();

        $this->postJson('/api/products', $this->validPayload($category))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Garden Rake')
            ->assertJsonPath('data.slug', 'garden-rake')
            ->assertJsonPath('data.price', 4999)
            ->assertJsonPath('data.stock', 0)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.category.id', $category->id);

        $this->assertDatabaseHas('products', [
            'sku' => 'RAKE-001',
            'price' => 4999,
            'stock' => 0,
            'is_active' => true,
            'category_id' => $category->id,
        ]);
    }

    public function test_admin_can_create_product_with_all_fields_in_subcategory(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $subcategory = Category::factory()->childOf(Category::factory()->create())->create();

        $this->postJson('/api/products', [
            ...$this->validPayload($subcategory),
            'slug' => 'rake-pro',
            'description' => 'A sturdy rake.',
            'stock' => 12,
            'is_active' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'rake-pro')
            ->assertJsonPath('data.stock', 12)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.category.id', $subcategory->id);
    }

    public function test_sku_is_normalized_to_uppercase(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/products', [...$this->validPayload(Category::factory()->create()), 'sku' => ' rake-001 '])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'RAKE-001');
    }

    public function test_create_requires_category_name_sku_and_price(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'name', 'sku', 'price']);

        $this->assertDatabaseCount('products', 0);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidFields(): array
    {
        return [
            'fractional price' => ['price', 49.99],
            'price as decimal string' => ['price', '49.99'],
            'price as integer string' => ['price', '4999'],
            'price as boolean' => ['price', true],
            'stock as boolean' => ['stock', true],
            'negative price' => ['price', -1],
            'price above column limit' => ['price', 4294967296],
            'negative stock' => ['stock', -1],
            'fractional stock' => ['stock', 1.5],
            'non-boolean is_active' => ['is_active', 'yes'],
            'invalid sku format' => ['sku', 'RAKE_001'],
            'invalid slug format' => ['slug', 'Garden Rake'],
            'missing category' => ['category_id', 999],
            'too long description' => ['description', str_repeat('a', 10001)],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_create_rejects_invalid_field(string $field, mixed $value): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/products', [...$this->validPayload(Category::factory()->create()), $field => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_create_rejects_taken_slug_and_sku(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Product::factory()->create(['slug' => 'garden-rake', 'sku' => 'RAKE-001']);

        $this->postJson('/api/products', [...$this->validPayload(Category::factory()->create()), 'sku' => 'rake-001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'sku']);
    }

    public function test_regular_user_gets_403_before_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/products', [])->assertForbidden();
    }

    public function test_regular_user_cannot_create_product(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/products', $this->validPayload(Category::factory()->create()))->assertForbidden();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_create_requires_token(): void
    {
        $this->postJson('/api/products', [])->assertUnauthorized();
    }
}
