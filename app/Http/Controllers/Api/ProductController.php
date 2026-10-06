<?php

namespace App\Http\Controllers\Api;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request, CatalogCache $catalogCache): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        /** @var User $user */
        $user = $request->user();

        $payload = $catalogCache->rememberProductsPage(
            $user->is_admin,
            Paginator::resolveCurrentPage(),
            fn (): array => ProductResource::collection(
                Product::query()
                    ->visibleTo($user)
                    ->with('category')
                    ->orderBy('name')
                    ->orderBy('id')
                    ->paginate(15)
                    // Links get cached for everyone, so they must not come from the request's Host header.
                    ->withPath(config()->string('app.url').'/'.$request->path())
            )->toResponse($request)->getData(true),
        );

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
