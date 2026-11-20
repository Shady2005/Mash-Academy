<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class PreferenceController extends Controller
{
    public function setLocale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,ar'],
        ]);

        $locale = $validated['locale'];
        $request->session()->put('locale', $locale);
        Cookie::queue('mash_locale', $locale, 60 * 24 * 365);

        return back();
    }

    public function setTheme(Request $request): RedirectResponse|JsonResponse
    {
        $current = $request->session()->get('theme', $request->cookie('mash_theme', 'light'));
        $requested = $request->input('theme');

        $theme = in_array($requested, ['light', 'dark'], true)
            ? $requested
            : ($current === 'dark' ? 'light' : 'dark');

        $request->session()->put('theme', $theme);
        Cookie::queue('mash_theme', $theme, 60 * 24 * 365);

        if ($request->expectsJson()) {
            return response()->json(['theme' => $theme]);
        }

        return back();
    }
}
