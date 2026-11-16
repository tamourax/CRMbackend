<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private SessionService $sessions) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }
        $this->sessions->start($request);

        return $this->user($request);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->localized('name'), 'email' => $user->email]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->sessions->revoke($request);

        return response()->json(['message' => __('auth.signed_out')]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $this->sessions->refresh($request);

        return $this->user($request);
    }
}
