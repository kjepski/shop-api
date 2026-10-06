<?php

namespace App\Actions\Products;

use App\Models\Product;

class UpdateProduct
{
    /**
     * @param  array{category_id?: int, name?: string, slug?: string, sku?: string, price?: int, description?: string|null, stock?: int, is_active?: bool}  $data
     */
    public function handle(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }
}
