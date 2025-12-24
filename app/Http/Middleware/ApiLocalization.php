<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class ApiLocalization
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');

        if ($locale === null || $locale === '') {
            $locale = $request->query('locale');
        }

        if ($locale === null || $locale === '') {
            $locale = config('languages.default', 'en');
        }

        $locale = substr($locale, 0, 2);

        $availableLanguages = config('languages.available');
        if (is_array($availableLanguages) &&
            array_key_exists($locale, $availableLanguages) &&
            isset($availableLanguages[$locale]['active']) &&
            $availableLanguages[$locale]['active'] === true) {
            App::setLocale($locale);
        } else {
            App::setLocale(config('languages.default', 'en'));
        }

        return $next($request);
    }
}
