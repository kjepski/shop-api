<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches rendered catalog list pages. Payloads are plain arrays, because the cache
 * refuses to unserialize PHP objects (see cache.serializable_classes).
 *
 * Keys contain only the page (and audience): only the unfiltered list is cached, because
 * filter combinations are unbounded. Any parameter that changes the result (filters,
 * sorting, per_page) must either bypass this cache or become part of the key.
 */
class CatalogCache
{
    private const CATEGORIES_TAG = 'catalog:categories';

    private const PRODUCTS_TAG = 'catalog:products';

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function rememberCategoriesPage(int $page, Closure $build): array
    {
        return $this->remember(self::CATEGORIES_TAG, "catalog:categories:page:{$page}", $page, $build);
    }

    /**
     * Admins see inactive products too, so they get their own cache entries.
     *
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function rememberProductsPage(bool $forAdmin, int $page, Closure $build): array
    {
        $audience = $forAdmin ? 'admin' : 'public';

        return $this->remember(self::PRODUCTS_TAG, "catalog:products:{$audience}:page:{$page}", $page, $build);
    }

    /**
     * Product lists embed their category, so a category change flushes them too.
     */
    public function flushCategories(): void
    {
        Cache::tags([self::CATEGORIES_TAG, self::PRODUCTS_TAG])->flush();
    }

    public function flushProducts(): void
    {
        Cache::tags([self::PRODUCTS_TAG])->flush();
    }

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function remember(string $tag, string $key, int $page, Closure $build): array
    {
        $cache = Cache::tags([$tag]);

        $payload = $cache->get($key);

        if (is_array($payload)) {
            /** @var array<string, mixed> $payload */
            return $payload;
        }

        $payload = $build();

        // Pages past the last one are empty; not caching them stops ?page=N from filling the cache.
        if ($page === 1 || $payload['data'] !== []) {
            $cache->put($key, $payload, config()->integer('cache.catalog_ttl'));
        }

        return $payload;
    }
}
