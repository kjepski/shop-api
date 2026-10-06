<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Support\CatalogCache;

class CreateProduct
{
    public function __construct(private CatalogCache $catalogCache) {}

    /**
     * @param  array{category_id: int, name: string, slug: string, sku: string, price: int, description?: string|null, stock?: int, is_active?: bool}  $data
     */
    public function handle(array $data): Product
    {
        $product = Product::create($data);

        $this->catalogCache->flushProducts();

        return $product;
    }
}
