<?php

namespace App\Actions\Categories;

use App\Models\Category;

class UpdateCategory
{
    /**
     * @param  array{name?: string, slug?: string, parent_id?: int|null}  $data
     */
    public function handle(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }
}
