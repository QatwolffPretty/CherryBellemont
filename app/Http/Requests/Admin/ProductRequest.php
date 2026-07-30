<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            // image remains accepted for older admin posts; new forms use images[].
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_alt_texts' => ['nullable', 'array', 'max:10'],
            'image_alt_texts.*' => ['nullable', 'string', 'max:255'],
            'primary_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'size_ids' => ['nullable', 'array'],
            'size_ids.*' => ['integer', 'exists:product_sizes,id'],
            'colour_ids' => ['nullable', 'array'],
            'colour_ids.*' => ['integer', 'exists:product_colours,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:product_tags,id'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.size_id' => ['nullable', 'integer', 'exists:product_sizes,id'],
            'variants.*.colour_id' => ['nullable', 'integer', 'exists:product_colours,id'],
            'variants.*.sku' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $variants = collect((array) $this->input('variants', []))
            ->map(function (array $variant): array {
                $variant['is_active'] = filter_var($variant['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

                return $variant;
            })->all();

        $this->merge([
            'featured' => $this->boolean('featured'),
            'category_ids' => array_values(array_filter((array) $this->input('category_ids', []))),
            'size_ids' => array_values(array_filter((array) $this->input('size_ids', []))),
            'colour_ids' => array_values(array_filter((array) $this->input('colour_ids', []))),
            'tag_ids' => array_values(array_filter((array) $this->input('tag_ids', []))),
            'variants' => $variants,
        ]);
    }
}
