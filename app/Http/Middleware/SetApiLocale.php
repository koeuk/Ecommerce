<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the API response language from ?lang= or the Accept-Language
 * header, so translatable model columns serialise in the caller's locale.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['en']);

        $locale = $request->query('lang')
            ?? $request->getPreferredLanguage($supported)
            ?? config('app.locale');

        if (in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
