<?php

namespace Tests\Fakes;

use App\Services\Ai\AiProviderInterface;

class FakeAiProvider implements AiProviderInterface
{
    public bool $configured = true;

    public array $call = ['name' => 'stage_totals', 'arguments' => ['stage' => 'negotiation']];

    public ?array $response = null;

    public array $contexts = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function chooseTool(string $question, array $tools): array
    {
        return $this->call;
    }

    public function summarize(string $task, array $context): array
    {
        $this->contexts[] = $context;

        return $this->response ?? ['sections' => [['title' => 'Overview', 'content' => 'The available records are shown in the verified facts. Review pending opportunities and recorded risks.']], 'source_keys' => array_slice($context['source_keys'], 0, 20)];
    }
}
