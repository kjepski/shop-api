<?php

namespace App\Actions\Categories;

use App\Exceptions\CategoryHasChildrenException;
use App\Models\Category;

class DeleteCategory
{
    /**
     * @throws CategoryHasChildrenException
     */
    public function handle(Category $category): void
    {
        if ($category->children()->exists()) {
            throw new CategoryHasChildrenException;
        }

        $category->delete();
    }
}
