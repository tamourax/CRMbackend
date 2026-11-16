<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceActiveSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        if ($request->hasSession() && $request->session()->has($guard->getName())) {
            $secret = $request->session()->get('auth_session_secret');
            $started = $request->session()->get('authenticated_at');
            $hash = $guard->user()?->auth_session_hash;
            $valid = is_string($secret) && is_string($hash)
                && hash_equals($hash, hash('sha256', $secret))
                && is_int($started) && $started <= now()->timestamp
                && now()->timestamp - $started < config('session.absolute_lifetime', 480) * 60;

            if (! $valid) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                throw new AuthenticationException;
            }
        }

        return $next($request);
    }
}
