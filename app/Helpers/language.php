<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

if (! function_exists('localized_route')) {
    function localized_route($name, $parameters = [], $absolute = true)
    {
        if (! isset($parameters['locale'])) {
            $parameters['locale'] = App::getLocale();
        }

        return route($name, $parameters, $absolute);
    }
}

if (! function_exists('get_url_for_locale')) {
    function get_url_for_locale($locale)
    {
        $currentRoute = Route::current();

        if (! $currentRoute) {
            return url($locale);
        }

        $routeName = $currentRoute->getName();

        if (! $routeName) {
            $path = $currentRoute->uri();
            $segments = explode('/', $path);

            if (count($segments) > 0) {
                $segments[0] = $locale;

                return url(implode('/', $segments));
            }

            return url($locale);
        }

        $parameters = $currentRoute->parameters();
        $parameters['locale'] = $locale;

        return route($routeName, $parameters);
    }
}

if (! function_exists('get_language_switchers')) {
    function get_language_switchers()
    {
        $languages = config('languages.available');
        $currentLocale = App::getLocale();
        $switchers = [];

        foreach ($languages as $code => $language) {
            if ($language['active']) {
                $switchers[] = [
                    'code' => $code,
                    'name' => $language['name'],
                    'native' => $language['native'],
                    'flag' => $language['flag'],
                    'url' => get_url_for_locale($code),
                    'active' => $currentLocale === $code,
                ];
            }
        }

        return $switchers;
    }
}
