<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;

/** Until a company pack is installed, every page leads to the setup screen. */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (! app(Settings::class)->installed() && ! $request->routeIs('setup', 'setup.*', 'brand.logo', 'locale')) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
