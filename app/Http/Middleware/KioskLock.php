<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * While kiosk mode is active the browser session can only reach kiosk screens, so a
 * worker at the tablet cannot wander into the supervisor's account.
 */
class KioskLock
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->get('kiosk.active') && ! $request->routeIs('kiosk.*', 'brand.logo', 'locale')) {
            return redirect()->route('kiosk.index');
        }

        return $next($request);
    }
}
