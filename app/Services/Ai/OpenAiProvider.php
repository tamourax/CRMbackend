<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class OpenAiProvider implements AiProviderInterface
{
    public function isConfigured(): bool
    {
        return config('ai.provider') === 'openai' && filled(config('ai.api_key'));
    }

    public function chooseTool(string $question, array $tools): array
    {
        $response = $this->request([
            'instructions' => AiPrompts::toolSelection(),
            'input' => [['role' => 'user', 'content' => $question]],
            'tools' => $tools, 'tool_choice' => 'required', 'parallel_tool_calls' => false,
        ]);
        $calls = array_values(array_filter($response['output'] ?? [], fn ($item) => ($item['type'] ?? null) === 'function_call'));
        if (count($calls) !== 1) {
            throw new AiUnavailable(__('crm.ai.unsupported_query'));
        }
        try {
            $arguments = json_decode($calls[0]['arguments'] ?? '', true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AiUnavailable(__('crm.ai.invalid_provider_query'));
        }
        if (! is_array($arguments) || ! is_string($calls[0]['name'] ?? null)) {
            throw new AiUnavailable(__('crm.ai.invalid_provider_query'));
        }

        return ['name' => $calls[0]['name'], 'arguments' => $arguments];
    }

    public function summarize(string $task, array $context): array
    {
        $response = $this->request([
            'instructions' => AiPrompts::summaryInstructions(),
            'input' => [['role' => 'user', 'content' => json_encode(['task' => $task, 'context' => $context], JSON_THROW_ON_ERROR)]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'crm_summary', 'strict' => true, 'schema' => AiPrompts::summarySchema()]],
        ]);
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? '') === 'output_text') {
                    try {
                        $result = json_decode($content['text'], true, 32, JSON_THROW_ON_ERROR);
                        if (is_array($result)) {
                            return $result;
                        }
                    } catch (JsonException) {
                        break;
                    }
                }
            }
        }
        throw new AiUnavailable(__('crm.ai.provider_unverified_response'));
    }

    private function request(array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailable(__('crm.ai.not_configured'));
        }
        try {
            $response = Http::withToken(config('ai.api_key'))->acceptJson()->withOptions(['verify' => config('ai.ca_bundle') ?: true])->connectTimeout(5)->timeout(config('ai.timeout'))->post('https://api.openai.com/v1/responses', [
                'model' => config('ai.model'), 'store' => false,
                'reasoning' => ['effort' => 'low'], 'max_output_tokens' => config('ai.max_output_tokens'), ...$payload,
            ]);
        } catch (ConnectionException) {
            throw new AiUnavailable(__('crm.ai.provider_unavailable'));
        }
        if (! $response->successful()) {
            Log::warning('AI provider request failed.', ['status' => $response->status()]);
            throw new AiUnavailable(__('crm.ai.provider_failed'));
        }
        $data = $response->json();
        if (! is_array($data) || ($data['status'] ?? null) !== 'completed') {
            throw new AiUnavailable(__('crm.ai.response_incomplete'));
        }

        return $data;
    }
}
