<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = setting('locales', ['el', 'en']);
        $locale = $request->session()->get('locale') ?? $request->user()?->locale ?? setting('default_locale', config('app.locale'));

        app()->setLocale(in_array($locale, $allowed, true) ? $locale : ($allowed[0] ?? 'en'));

        return $next($request);
    }
}
