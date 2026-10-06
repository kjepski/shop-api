<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        // Enough regular users for the admin list to paginate; created after the fixed
        // accounts so a random email can never take one of their addresses.
        User::factory(20)->create();

        Category::factory(3)
            ->has(Category::factory(2)->has(Product::factory(5)), 'children')
            ->create();
    }
}
