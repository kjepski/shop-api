<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->product()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => Str::upper(trim($this->input('sku')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->product();

        return [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product)],
            'sku' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', Rule::unique('products', 'sku')->ignore($product)],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            // Price in grosze; the upper bound is the unsigned INT column limit.
            'price' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, digits and single hyphens.',
            'sku.regex' => 'The SKU may only contain letters, digits and single hyphens.',
            'price.integer' => 'The price must be a whole number of grosze.',
        ];
    }

    private function product(): Product
    {
        /** @var Product */
        return $this->route('product');
    }
}
