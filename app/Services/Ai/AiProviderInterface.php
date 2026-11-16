<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    public function isConfigured(): bool;

    /** @return array{name: string, arguments: array} */
    public function chooseTool(string $question, array $tools): array;

    /** @return array{sections: array, source_keys: array} */
    public function summarize(string $task, array $context): array;
}
