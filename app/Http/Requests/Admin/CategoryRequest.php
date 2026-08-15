<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $locales = config('app.supported_locales', ['en']);

        $rules = [
            'parent_id' => [
                'nullable', 'integer', 'exists:categories,id',
                // A category cannot be its own parent.
                Rule::notIn($category ? [$category->id] : []),
            ],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'description' => ['nullable', 'array'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        foreach ($locales as $locale) {
            $rules["name.{$locale}"] ??= ['nullable', 'string', 'max:255'];
            $rules["description.{$locale}"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $category = $this->route('category');
            $parentId = $this->input('parent_id');

            if (! $category || ! $parentId) {
                return;
            }

            // Reparenting under a descendant would orphan the subtree.
            if ($this->isDescendant($category->id, (int) $parentId)) {
                $validator->errors()->add(
                    'parent_id',
                    'A category cannot be moved beneath one of its own descendants.'
                );
            }
        });
    }

    private function isDescendant(int $ancestorId, int $candidateId): bool
    {
        $current = \App\Models\Category::find($candidateId);

        while ($current?->parent_id) {
            if ($current->parent_id === $ancestorId) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    public function attributes(): array
    {
        return ['name.en' => 'English name', 'parent_id' => 'parent category'];
    }
}
