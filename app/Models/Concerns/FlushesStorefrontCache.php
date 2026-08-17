<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * The navigation tree and sitemap are cached hard — they are read on every
 * visit and change a handful of times a year. Without this, an edit in the
 * admin panel would not reach the storefront until the cache expired.
 *
 * A model opts in by listing the keys it invalidates.
 */
trait FlushesStorefrontCache
{
    protected static function bootFlushesStorefrontCache(): void
    {
        $flush = function ($model) {
            foreach ($model->storefrontCacheKeys() as $key) {
                Cache::forget($key);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    /** @return array<string> */
    protected function storefrontCacheKeys(): array
    {
        return ['storefront.sitemap'];
    }
}
