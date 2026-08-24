<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Localization
{
    /**
     * Handle an incoming request.
     * Derive the locale from the browser's Accept-Language header on every
     * request - there is no manual language switcher to override it.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $fallback = config('app.fallback_locale');
        $available = array_values(config('app.available_locales'));

        // put the fallback first so getPreferredLanguage() lands on it
        // when the browser doesn't ask for any of our available locales
        $locales = array_unique(array_merge([$fallback], $available));

        $locale = $request->getPreferredLanguage($locales);

        App::setLocale($locale);

        // Small hack to set the LC_TIME. de/en/es do not work on all systems
        setlocale(LC_TIME, match ($locale) {
            'de' => 'de_DE',
            'es' => 'es_ES',
            default => 'en_US',
        });

        return $next($request);
    }
}
