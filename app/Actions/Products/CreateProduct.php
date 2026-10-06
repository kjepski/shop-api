<?php

namespace App\Actions\Products;

use App\Models\Product;

class CreateProduct
{
    /**
     * @param  array<string, mixed>  $data  Validated product attributes.
     */
    public function handle(array $data): Product
    {
        return Product::create($data);
    }
}
