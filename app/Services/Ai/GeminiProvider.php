<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class GeminiProvider implements AiProviderInterface
{
    private ?float $deadline = null;

    public function isConfigured(): bool
    {
        return config('ai.provider') === 'gemini' && filled(config('ai.gemini_api_key'));
    }

    public function chooseTool(string $question, array $tools): array
    {
        $declarations = array_map(fn (array $tool) => [
            'name' => $tool['name'], 'description' => $tool['description'],
            'parametersJsonSchema' => $tool['parameters'],
        ], $tools);
        $parts = $this->request([
            'systemInstruction' => ['parts' => [['text' => AiPrompts::toolSelection()]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $question]]]],
            'tools' => [['functionDeclarations' => $declarations]],
            'toolConfig' => ['functionCallingConfig' => ['mode' => 'ANY', 'allowedFunctionNames' => array_column($declarations, 'name')]],
        ]);
        $calls = array_values(array_filter($parts, fn (array $part) => isset($part['functionCall'])));
        if (count($calls) !== 1) {
            throw new AiUnavailable(__('crm.ai.unsupported_query'));
        }
        $call = $calls[0]['functionCall'];
        $arguments = $call['args'] ?? [];
        if (! is_string($call['name'] ?? null) || ! is_array($arguments)) {
            throw new AiUnavailable(__('crm.ai.invalid_provider_query'));
        }

        return ['name' => $call['name'], 'arguments' => $arguments];
    }

    public function summarize(string $task, array $context): array
    {
        $parts = $this->request([
            'systemInstruction' => ['parts' => [['text' => AiPrompts::summaryInstructions()]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => json_encode(['task' => $task, 'context' => $context], JSON_THROW_ON_ERROR)]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => AiPrompts::summarySchema()],
        ]);
        $text = implode('', array_column(array_filter($parts, fn (array $part) => isset($part['text']) && ! ($part['thought'] ?? false)), 'text'));
        try {
            $result = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
            if (is_array($result)) {
                return $result;
            }
        } catch (JsonException) {
            // Invalid provider JSON must never become an unverified answer.
        }

        throw new AiUnavailable(__('crm.ai.provider_unverified_response'));
    }

    private function request(array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailable(__('crm.ai.not_configured'));
        }
        $this->deadline ??= microtime(true) + 55;
        $remaining = $this->deadline - microtime(true);
        if ($remaining < 1) {
            throw new AiUnavailable(__('crm.ai.request_timeout'));
        }
        try {
            $response = Http::withHeaders(['x-goog-api-key' => config('ai.gemini_api_key')])->acceptJson()
                ->withOptions(['verify' => config('ai.ca_bundle') ?: true])
                ->connectTimeout(5)->timeout(min(config('ai.gemini_timeout'), $remaining))
                ->retry(2, 300, function (\Exception $exception, PendingRequest $request): bool {
                    $remaining = $this->deadline - microtime(true) - 0.3;
                    if ($exception instanceof RequestException && in_array($exception->response->status(), [500, 502, 503, 504], true) && $remaining > 1) {
                        $request->timeout(min(config('ai.gemini_timeout'), $remaining));

                        return true;
                    }

                    return false;
                }, throw: false)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode(config('ai.gemini_model')).':generateContent', [
                    ...$payload,
                    'generationConfig' => [
                        'maxOutputTokens' => config('ai.max_output_tokens'),
                        'thinkingConfig' => str_starts_with(config('ai.gemini_model'), 'gemini-2.5-') ? ['thinkingBudget' => 0] : ['thinkingLevel' => 'minimal'],
                        ...($payload['generationConfig'] ?? []),
                    ],
                ]);
        } catch (ConnectionException) {
            throw new AiUnavailable(__('crm.ai.provider_unavailable'));
        }
        if (! $response->successful()) {
            Log::warning('AI provider request failed.', ['provider' => 'gemini', 'status' => $response->status()]);
            if ($response->status() === 429) {
                throw new AiUnavailable(__('crm.ai.provider_quota'));
            }
            throw new AiUnavailable(__('crm.ai.provider_failed'));
        }
        $data = $response->json();
        $candidate = is_array($data) ? ($data['candidates'][0] ?? null) : null;
        if (! is_array($candidate) || ($candidate['finishReason'] ?? null) !== 'STOP' || ! is_array($candidate['content']['parts'] ?? null)) {
            throw new AiUnavailable(__('crm.ai.response_incomplete'));
        }

        return $candidate['content']['parts'];
    }
}
