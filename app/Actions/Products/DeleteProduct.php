<?php

namespace App\Actions\Products;

use App\Models\Product;

class DeleteProduct
{
    public function handle(Product $product): void
    {
        $product->delete();
    }
}
