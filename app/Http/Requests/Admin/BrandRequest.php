<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware already gates on the permission.
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')?->id;
        $locales = config('app.supported_locales', ['en']);

        $rules = [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('brands', 'slug')->ignore($brandId),
            ],
            'description' => ['nullable', 'array'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        foreach ($locales as $locale) {
            $rules["name.{$locale}"] ??= ['nullable', 'string', 'max:255'];
            $rules["description.{$locale}"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['name.en' => 'English name'];
    }
}
