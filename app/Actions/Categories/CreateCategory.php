<?php

namespace App\Actions\Categories;

use App\Models\Category;

class CreateCategory
{
    /**
     * @param  array{name: string, slug: string, parent_id?: int|null}  $data
     */
    public function handle(array $data): Category
    {
        return Category::create($data);
    }
}
