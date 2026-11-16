<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskAssistantRequest;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiProviderInterface;
use App\Services\CrmQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function status(AiProviderInterface $provider): JsonResponse
    {
        return response()->json(['data' => ['ai_configured' => $provider->isConfigured()]]);
    }

    public function customer(Request $request, string $id, CrmQueryService $queries, AiAssistant $assistant): JsonResponse
    {
        $customer = $queries->findCustomer($request->user(), $id);

        return response()->json(['data' => $assistant->customer($customer)]);
    }

    public function report(Request $request, AiAssistant $assistant): JsonResponse
    {
        return response()->json(['data' => $assistant->report($request->user())]);
    }

    public function ask(AskAssistantRequest $request, AiAssistant $assistant): JsonResponse
    {
        return response()->json(['data' => $assistant->ask($request->user(), $request->validated('question'))]);
    }
}
