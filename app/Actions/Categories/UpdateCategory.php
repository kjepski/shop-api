<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Support\CatalogCache;

class UpdateCategory
{
    public function __construct(private CatalogCache $catalogCache) {}

    /**
     * @param  array{name?: string, slug?: string, parent_id?: int|null}  $data
     */
    public function handle(Category $category, array $data): Category
    {
        $category->update($data);

        $this->catalogCache->flushCategories();

        return $category;
    }
}
