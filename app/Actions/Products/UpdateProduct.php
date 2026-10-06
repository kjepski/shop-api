<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Support\CatalogCache;

class UpdateProduct
{
    public function __construct(private CatalogCache $catalogCache) {}

    /**
     * @param  array{category_id?: int, name?: string, slug?: string, sku?: string, price?: int, description?: string|null, stock?: int, is_active?: bool}  $data
     */
    public function handle(Product $product, array $data): Product
    {
        $product->update($data);

        $this->catalogCache->flushProducts();

        return $product;
    }
}
