<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductCatalogueAttributeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $model = $this->route('product_size') ?? $this->route('product_colour') ?? $this->route('product_tag');
        $type = $this->route()->getName();
        $table = str_contains((string) $type, 'product-colours') ? 'product_colours' : (str_contains((string) $type, 'product-tags') ? 'product_tags' : 'product_sizes');
        $key = $table === 'product_sizes' ? 'code' : 'slug';

        return [
            'name' => ['required', 'string', 'max:80'],
            $key => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]+(?:[-_][A-Za-z0-9]+)*$/', Rule::unique($table, $key)->ignore($model)],
            'hex_code' => [$table === 'product_colours' ? 'nullable' : 'prohibited', 'regex:/^#[A-Fa-f0-9]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $routeName = (string) $this->route()?->getName();
        $values = ['is_active' => $this->boolean('is_active')];

        if (str_contains($routeName, 'product-sizes')) {
            $code = trim((string) $this->input('code'));
            $values['code'] = $code === '' ? null : Str::upper($code);
        } else {
            $slug = trim((string) $this->input('slug'));
            $values['slug'] = $slug === '' ? null : Str::slug($slug);
        }

        $this->merge($values);
    }
}
