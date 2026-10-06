<?php

namespace App\Actions\Products;

use App\Models\Product;

class UpdateProduct
{
    /**
     * @param  array<string, mixed>  $data  Validated product attributes.
     */
    public function handle(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }
}
