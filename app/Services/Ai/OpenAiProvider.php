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
            throw new AiUnavailable('The assistant could not select a supported query. Please rephrase your question.');
        }
        try {
            $arguments = json_decode($calls[0]['arguments'] ?? '', true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AiUnavailable('The assistant returned an invalid query. Please try again.');
        }
        if (! is_array($arguments) || ! is_string($calls[0]['name'] ?? null)) {
            throw new AiUnavailable('The assistant returned an invalid query. Please try again.');
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
        throw new AiUnavailable('The AI response could not be verified. Please try again.');
    }

    private function request(array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailable('AI is not configured. Add a valid API key on the backend to enable live answers.');
        }
        try {
            $response = Http::withToken(config('ai.api_key'))->acceptJson()->withOptions(['verify' => config('ai.ca_bundle') ?: true])->connectTimeout(5)->timeout(config('ai.timeout'))->post('https://api.openai.com/v1/responses', [
                'model' => config('ai.model'), 'store' => false,
                'reasoning' => ['effort' => 'low'], 'max_output_tokens' => config('ai.max_output_tokens'), ...$payload,
            ]);
        } catch (ConnectionException) {
            throw new AiUnavailable('The AI provider is temporarily unreachable. Please try again.');
        }
        if (! $response->successful()) {
            Log::warning('AI provider request failed.', ['status' => $response->status()]);
            throw new AiUnavailable('The AI provider could not complete the request. Check configuration or try again later.');
        }
        $data = $response->json();
        if (! is_array($data) || ($data['status'] ?? null) !== 'completed') {
            throw new AiUnavailable('The AI response was incomplete. Please try a more specific question.');
        }

        return $data;
    }
}
