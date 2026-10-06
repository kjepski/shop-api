<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Support\CatalogCache;

class DeleteProduct
{
    public function __construct(private CatalogCache $catalogCache) {}

    public function handle(Product $product): void
    {
        $product->delete();

        $this->catalogCache->flushProducts();
    }
}
