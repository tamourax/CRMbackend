<?php

namespace App\Http\Middleware;

use App\Services\SessionService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceActiveSession
{
    public function __construct(private SessionService $sessions) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        if ($request->hasSession() && $request->session()->has($guard->getName()) && ! $this->sessions->isValid($request)) {
            $this->sessions->invalidate($request);
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
