<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

final class LocaleAwareAuthentication
{
    public function handle($request, Closure $next, ...$guards)
    {
        if (! Auth::check()) {
            return redirect()->route('login', ['locale' => App::getLocale()]);
        }

        return $next($request);
    }
}
