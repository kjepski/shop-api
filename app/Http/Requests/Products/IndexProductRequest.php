<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\Concerns\DetectsFilterInput;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    use DetectsFilterInput;

    /**
     * Query parameters that narrow the list; together with sort, must match the keys of rules().
     */
    public const FILTERS = ['search', 'category_id', 'min_price', 'max_price', 'in_stock'];

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Product::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:2', 'max:100'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            // Prices in grosze; query strings are always text, so plain (non-strict) integer is right here.
            'min_price' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            // gte fails when the other bound is missing or invalid, so it only applies when min_price is a number.
            'max_price' => ['nullable', 'integer', 'min:0', 'max:4294967295', Rule::when(is_numeric($this->input('min_price')), 'gte:min_price')],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', Rule::in(Product::sortValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_price.gte' => 'The max price must be greater than or equal to the min price.',
        ];
    }

    /**
     * The requested sort, or the default one; never a filter, so it does not count as a search.
     */
    public function sort(): string
    {
        return $this->filled('sort') ? $this->string('sort')->toString() : Product::DEFAULT_SORT;
    }

    /**
     * Query parameters for pagination links: applied filters and a non-default sort only,
     * so unknown parameters never end up in (cached) links.
     *
     * @return array<string, string|int|true>
     */
    public function linkParameters(): array
    {
        $sort = $this->sort();

        return $sort === Product::DEFAULT_SORT ? $this->filters() : [...$this->filters(), 'sort' => $sort];
    }

    /**
     * Only the filters that are actually applied, typed, so they can drive the query and pagination links.
     *
     * @return array{search?: string, category_id?: int, min_price?: int, max_price?: int, in_stock?: true}
     */
    public function filters(): array
    {
        $filters = [];

        if ($this->filled('search')) {
            $filters['search'] = $this->string('search')->toString();
        }

        foreach (['category_id', 'min_price', 'max_price'] as $key) {
            if ($this->filled($key)) {
                $filters[$key] = $this->integer($key);
            }
        }

        if ($this->boolean('in_stock')) {
            $filters['in_stock'] = true;
        }

        return $filters;
    }
}
