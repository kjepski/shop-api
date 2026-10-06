<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
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
