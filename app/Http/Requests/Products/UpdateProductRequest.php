<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Returns the full policy response, so an inactive product yields 404 instead of 403.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->product());
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
            // Price in grosze as a JSON integer (strict, so true or "49.99" are rejected); the upper bound is the unsigned INT column limit.
            'price' => ['sometimes', 'required', 'integer:strict', 'min:0', 'max:4294967295'],
            'stock' => ['sometimes', 'required', 'integer:strict', 'min:0', 'max:4294967295'],
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
