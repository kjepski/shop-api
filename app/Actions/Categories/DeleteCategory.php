<?php

namespace App\Actions\Categories;

use App\Exceptions\CategoryHasChildrenException;
use App\Exceptions\CategoryHasProductsException;
use App\Models\Category;
use App\Support\CatalogCache;

class DeleteCategory
{
    public function __construct(private CatalogCache $catalogCache) {}

    /**
     * @throws CategoryHasChildrenException
     * @throws CategoryHasProductsException
     */
    public function handle(Category $category): void
    {
        if ($category->children()->exists()) {
            throw new CategoryHasChildrenException;
        }

        if ($category->products()->exists()) {
            throw new CategoryHasProductsException;
        }

        $category->delete();

        $this->catalogCache->flushCategories();
    }
}
