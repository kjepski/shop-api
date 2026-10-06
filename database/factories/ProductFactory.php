<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name),
            'sku' => Str::upper(fake()->unique()->bothify('???-#####')),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(100, 100_000),
            'stock' => fake()->numberBetween(0, 50),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the product is hidden from regular users.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the product belongs to the given category.
     */
    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
