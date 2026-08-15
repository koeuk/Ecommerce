<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware already gates on the permission.
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $locales = config('app.supported_locales', ['en']);

        $rules = [
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:400', Rule::unique('products', 'slug')->ignore($productId)],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'short_description' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'array'],
            'meta_description' => ['nullable', 'array'],

            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],

            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'gt:price'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],

            'status' => ['required', Rule::in(ProductStatus::values())],
            'condition' => ['required', Rule::in(['new', 'refurbished', 'used'])],
            'is_featured' => ['boolean'],

            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'release_year' => ['nullable', 'integer', 'min:1990', 'max:'.(now()->year + 1)],

            'weight' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],

            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],

            // Variants — each row is a sellable SKU
            'variants' => ['array'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.label' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.allow_backorder' => ['boolean'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.attribute_value_ids' => ['array'],
            'variants.*.attribute_value_ids.*' => ['nullable', 'integer', 'exists:attribute_values,id'],

            // Spec sheet — descriptive only, never creates a SKU
            'specifications' => ['array'],
            'specifications.*.group' => ['nullable', 'array'],
            'specifications.*.key' => ['required', 'array'],
            'specifications.*.key.en' => ['required', 'string', 'max:255'],
            'specifications.*.value' => ['nullable', 'array'],

            'images' => ['array', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];

        foreach ($locales as $locale) {
            $rules["title.{$locale}"] ??= ['nullable', 'string', 'max:255'];
            $rules["short_description.{$locale}"] = ['nullable', 'string', 'max:500'];
            $rules["description.{$locale}"] = ['nullable', 'string', 'max:50000'];
            $rules["meta_title.{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["meta_description.{$locale}"] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        // An empty numeric input arrives as "", which fails `numeric`.
        foreach (['compare_at_price', 'cost_price', 'warranty_months', 'release_year',
            'weight', 'length', 'width', 'height', 'brand_id', 'category_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $variants = $this->input('variants', []);

            if ($variants === []) {
                return;
            }

            // Two variants sharing an option combination would be ambiguous
            // for the storefront picker.
            $seen = [];

            foreach ($variants as $index => $variant) {
                $combo = collect($variant['attribute_value_ids'] ?? [])
                    ->filter()
                    ->sort()
                    ->implode('-');

                if ($combo === '') {
                    continue;
                }

                if (in_array($combo, $seen, true)) {
                    $validator->errors()->add(
                        "variants.{$index}.attribute_value_ids",
                        'Another variant already uses this exact combination of options.'
                    );
                }

                $seen[] = $combo;
            }
        });
    }

    public function attributes(): array
    {
        return [
            'title.en' => 'English title',
            'variants.*.sku' => 'variant SKU',
            'specifications.*.key.en' => 'specification name',
        ];
    }
}
