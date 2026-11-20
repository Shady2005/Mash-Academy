<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleAndTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->cookie('mash_locale', config('app.locale', 'en')));
        if (! in_array($locale, ['en', 'ar'], true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        $theme = $request->session()->get('theme', $request->cookie('mash_theme', 'light'));
        if (! in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }

        View::share('currentLocale', $locale);
        View::share('isRtl', $locale === 'ar');
        View::share('currentTheme', $theme);

        return $next($request);
    }
}
