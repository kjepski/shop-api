<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProducts
{
    /**
     * @param  array{search?: string, category_id?: int, min_price?: int, max_price?: int, in_stock?: true}  $filters
     * @param  string  $sort  one of Product::sortValues()
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(User $user, array $filters, string $sort): LengthAwarePaginator
    {
        return Product::query()
            ->visibleTo($user)
            ->when(isset($filters['search']), fn (Builder $query) => $query->search($filters['search']))
            ->when(isset($filters['category_id']), fn (Builder $query) => $query->inCategory($filters['category_id']))
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null)
            ->when(isset($filters['in_stock']), fn (Builder $query) => $query->inStock())
            ->with('category')
            ->sorted($sort)
            ->paginate(15);
    }
}
