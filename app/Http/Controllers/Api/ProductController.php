<?php

namespace App\Http\Controllers\Api;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\ListProducts;
use App\Actions\Products\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\IndexProductRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request, ListProducts $listProducts, CatalogCache $catalogCache): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->filters();
        $sort = $request->sort();

        $render = fn (): array => ProductResource::collection(
            $listProducts->handle($user, $filters, $sort)
                // Links get cached for everyone, so they must not come from the request's Host header.
                ->withPath(config()->string('app.url').'/'.$request->path())
                ->appends($request->linkParameters())
        )->toResponse($request)->getData(true);

        // Filtered lists skip the cache: search terms and price ranges have unbounded combinations.
        // Sorts are a short fixed list, so each one gets its own cache entries.
        $payload = $filters === []
            ? $catalogCache->rememberProductsPage($user->is_admin, $sort, Paginator::resolveCurrentPage(), $render)
            : $render();

        return response()->json($payload);
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        /** @var array{category_id: int, name: string, slug: string, sku: string, price: int, description?: string|null, stock?: int, is_active?: bool} $data */
        $data = $request->validated();

        $product = $createProduct->handle($data);

        return ProductResource::make($product->load('category'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return ProductResource::make($product->load('category'));
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $updateProduct): ProductResource
    {
        /** @var array{category_id?: int, name?: string, slug?: string, sku?: string, price?: int, description?: string|null, stock?: int, is_active?: bool} $data */
        $data = $request->validated();

        $product = $updateProduct->handle($product, $data);

        return ProductResource::make($product->load('category'));
    }

    public function destroy(Product $product, DeleteProduct $deleteProduct): Response
    {
        Gate::authorize('delete', $product);

        $deleteProduct->handle($product);

        return response()->noContent();
    }
}
