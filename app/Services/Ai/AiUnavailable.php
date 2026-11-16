<?php

namespace App\Services\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiUnavailable extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 503);
    }
}
