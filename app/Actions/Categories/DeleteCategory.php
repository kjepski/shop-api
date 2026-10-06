<?php

namespace App\Actions\Categories;

use App\Exceptions\CategoryHasChildrenException;
use App\Exceptions\CategoryHasProductsException;
use App\Models\Category;

class DeleteCategory
{
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
    }
}
