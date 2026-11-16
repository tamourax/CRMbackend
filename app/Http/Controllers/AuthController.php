<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }
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

        return $this->user($request);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->localized('name'), 'email' => $user->email]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $secret = $request->session()->get('auth_session_secret');
        if (is_string($secret)) {
            $request->user()->newQuery()->whereKey($request->user()->id)
                ->where('auth_session_hash', hash('sha256', $secret))
                ->update(['auth_session_hash' => null]);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => __('auth.signed_out')]);
    }

    public function refresh(Request $request): JsonResponse
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

        return $this->user($request);
    }
}
