<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

final class LanguageController extends Controller
{
    public function switch($lang): RedirectResponse
    {
        $availableLanguages = config('languages.available');

        if (is_array($availableLanguages)
            && array_key_exists($lang, $availableLanguages)
            && isset($availableLanguages[$lang]['active'])
            && $availableLanguages[$lang]['active'] === true
        ) {
            Session::put('locale', $lang);
            App::setLocale($lang);
        }

        return redirect()->back();
    }
}
