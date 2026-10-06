<?php

namespace App\Http\Controllers\Api;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Request $request, CatalogCache $catalogCache): JsonResponse
    {
        Gate::authorize('viewAny', Category::class);

        $payload = $catalogCache->rememberCategoriesPage(
            Paginator::resolveCurrentPage(),
            fn (): array => CategoryResource::collection(
                Category::query()
                    ->orderBy('name')
                    ->orderBy('id')
                    ->paginate(15)
                    // Links get cached for everyone, so they must not come from the request's Host header.
                    ->withPath(config()->string('app.url').'/'.$request->path())
            )->toResponse($request)->getData(true),
        );

        return response()->json($payload);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): JsonResponse
    {
        /** @var array{name: string, slug: string, parent_id?: int|null} $data */
        $data = $request->validated();

        return CategoryResource::make($createCategory->handle($data))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Category $category): CategoryResource
    {
        Gate::authorize('view', $category);

        return CategoryResource::make($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $updateCategory): CategoryResource
    {
        /** @var array{name?: string, slug?: string, parent_id?: int|null} $data */
        $data = $request->validated();

        return CategoryResource::make($updateCategory->handle($category, $data));
    }

    public function destroy(Category $category, DeleteCategory $deleteCategory): Response
    {
        Gate::authorize('delete', $category);

        $deleteCategory->handle($category);

        return response()->noContent();
    }
}
