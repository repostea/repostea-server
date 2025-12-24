<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        $availableLanguages = config('languages.available');

        if ($locale !== null
            && is_array($availableLanguages)
            && array_key_exists($locale, $availableLanguages)
            && isset($availableLanguages[$locale]['active'])
            && $availableLanguages[$locale]['active'] === true
        ) {
            App::setLocale($locale);
            Session::put('locale', $locale);
        } else {
            $defaultLocale = config('languages.default', 'es');
            $path = $request->path();

            // Check if the first segment is a disabled/invalid locale
            $firstSegment = explode('/', $path)[0];
            if (is_array($availableLanguages) && array_key_exists($firstSegment, $availableLanguages)) {
                // It's a valid locale code but disabled, redirect to default locale home
                return redirect('/' . $defaultLocale);
            }

            if ($path !== $defaultLocale && ! str_starts_with($path, $defaultLocale . '/')) {
                $redirectPath = $defaultLocale;
                if ($path !== '/') {
                    $redirectPath .= '/' . $path;
                }

                return redirect($redirectPath);
            }
        }

        return $next($request);
    }
}
