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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        /** @var User $user */
        $user = $request->user();

        $products = Product::query()
            ->visibleTo($user)
            ->with('category')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        $product = $createProduct->handle($request->validated());

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
        $product = $updateProduct->handle($product, $request->validated());

        return ProductResource::make($product->load('category'));
    }

    public function destroy(Product $product, DeleteProduct $deleteProduct): Response
    {
        Gate::authorize('delete', $product);

        $deleteProduct->handle($product);

        return response()->noContent();
    }
}
