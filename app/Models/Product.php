<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'name', 'slug', 'sku', 'description', 'price', 'stock', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Mirrors the database defaults, so a freshly created product has the attributes without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stock' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'price' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Admins see every product, everyone else only active ones.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_admin) {
            $query->where('is_active', true);
        }
    }

    /**
     * Case-insensitive fragment of the name or SKU; % and _ in the term match literally.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $pattern = '%'.addcslashes($term, '\\%_').'%';

        $query->where(function (Builder $query) use ($pattern): void {
            $query->where('name', 'like', $pattern)->orWhere('sku', 'like', $pattern);
        });
    }

    /**
     * Products of the category or of any of its subcategories, in a single query.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeInCategory(Builder $query, int $categoryId): void
    {
        $query->whereIn(
            'category_id',
            Category::query()->select('id')->whereKey($categoryId)->orWhere('parent_id', $categoryId),
        );
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopePriceBetween(Builder $query, ?int $min, ?int $max): void
    {
        $query
            ->when($min !== null, fn (Builder $query) => $query->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $query) => $query->where('price', '<=', $max));
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeInStock(Builder $query): void
    {
        $query->where('stock', '>', 0);
    }
}
