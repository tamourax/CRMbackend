<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $preferredLocale = $request->getPreferredLanguage(['ar', 'en']) ?? 'en';
        app()->setLocale(str_starts_with(strtolower($preferredLocale), 'ar') ? 'ar' : 'en');

        return $next($request);
    }
}
