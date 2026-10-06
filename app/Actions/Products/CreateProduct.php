<?php

namespace App\Actions\Products;

use App\Models\Product;

class CreateProduct
{
    /**
     * @param  array{category_id: int, name: string, slug: string, sku: string, price: int, description?: string|null, stock?: int, is_active?: bool}  $data
     */
    public function handle(array $data): Product
    {
        return Product::create($data);
    }
}
