<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }

        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => Str::upper(trim($this->input('sku')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', Rule::unique('products', 'sku')],
            'description' => ['nullable', 'string', 'max:10000'],
            // Price in grosze as a JSON integer (strict, so true or "49.99" are rejected); the upper bound is the unsigned INT column limit.
            'price' => ['required', 'integer:strict', 'min:0', 'max:4294967295'],
            'stock' => ['sometimes', 'integer:strict', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'A slug could not be generated from the name. Provide a slug explicitly.',
            'slug.unique' => 'The slug is already taken. Provide a different slug or name.',
            'slug.regex' => 'The slug may only contain lowercase letters, digits and single hyphens.',
            'sku.regex' => 'The SKU may only contain letters, digits and single hyphens.',
            'price.integer' => 'The price must be a whole number of grosze.',
        ];
    }
}
