<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->category()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->category();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category)],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::notIn([$category->id]),
                // Only top-level categories can be parents, which keeps the tree two levels deep and free of cycles.
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                    if ($value !== null && $category->children()->exists()) {
                        $fail('A category with subcategories cannot have a parent.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'A category cannot be its own parent.',
            'parent_id.exists' => 'The parent must be an existing top-level category.',
        ];
    }

    private function category(): Category
    {
        /** @var Category */
        return $this->route('category');
    }
}
