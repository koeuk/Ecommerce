<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a unique slug from a source attribute on create.
 *
 * Translatable models store the source as JSON, so the English value is used
 * — a slug is a single canonical URL and is never translated.
 */
trait GeneratesSlug
{
    protected static function bootGeneratesSlug(): void
    {
        static::creating(function ($model) {
            if (blank($model->slug)) {
                $model->slug = $model->generateUniqueSlug();
            }
        });
    }

    public function generateUniqueSlug(): string
    {
        $source = $this->getAttribute($this->slugSourceColumn());

        if (is_array($source)) {
            $source = $source['en'] ?? reset($source);
        }

        $base = Str::slug((string) $source) ?: Str::random(8);
        $slug = $base;
        $suffix = 1;

        while (static::withoutGlobalScopes()
            ->where('slug', $slug)
            ->whereKeyNot($this->getKey())
            ->exists()
        ) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }

    protected function slugSourceColumn(): string
    {
        return property_exists($this, 'slugSource') ? $this->slugSource : 'name';
    }
}
