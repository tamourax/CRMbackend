<?php

namespace App\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionService
{
    public function start(Request $request): void
    {
        $request->session()->regenerate();
        $secret = Str::random(64);
        DB::transaction(function () use ($request, $secret): void {
            $request->user()->forceFill(['auth_session_hash' => hash('sha256', $secret)])->save();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table'))
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $request->session()->getId())->delete();
            }
        });
        $request->session()->put('auth_session_secret', $secret);
        $request->session()->put('authenticated_at', now()->timestamp);
    }

    public function isValid(Request $request): bool
    {
        $secret = $request->session()->get('auth_session_secret');
        $started = $request->session()->get('authenticated_at');
        $hash = Auth::guard('web')->user()?->auth_session_hash;

        return is_string($secret) && is_string($hash)
            && hash_equals($hash, hash('sha256', $secret))
            && is_int($started) && $started <= now()->timestamp
            && now()->timestamp - $started < config('session.absolute_lifetime', 480) * 60;
    }

    public function refresh(Request $request): void
    {
        $currentSecret = $request->session()->get('auth_session_secret');
        $nextSecret = Str::random(64);
        $updated = is_string($currentSecret) && $request->user()->newQuery()
            ->whereKey($request->user()->id)
            ->where('auth_session_hash', hash('sha256', $currentSecret))
            ->update(['auth_session_hash' => hash('sha256', $nextSecret)]) === 1;
        if (! $updated) {
            throw new AuthenticationException;
        }
        $request->session()->put('auth_session_secret', $nextSecret);
        $request->session()->regenerate(true);
    }

    public function revoke(Request $request): void
    {
        $secret = $request->session()->get('auth_session_secret');
        if (is_string($secret)) {
            $request->user()->newQuery()->whereKey($request->user()->id)
                ->where('auth_session_hash', hash('sha256', $secret))
                ->update(['auth_session_hash' => null]);
        }
        $this->invalidate($request);
    }

    public function invalidate(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
