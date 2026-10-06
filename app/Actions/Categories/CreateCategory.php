<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Support\CatalogCache;

class CreateCategory
{
    public function __construct(private CatalogCache $catalogCache) {}

    /**
     * @param  array{name: string, slug: string, parent_id?: int|null}  $data
     */
    public function handle(array $data): Category
    {
        $category = Category::create($data);

        $this->catalogCache->flushCategories();

        return $category;
    }
}
