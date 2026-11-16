<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\User;
use App\Services\SalesReportService;

class AiAssistant
{
    public function __construct(private AiProviderInterface $provider, private SalesReportService $reports, private ContextBuilder $contexts, private DealToolCatalog $catalog, private DealToolExecutor $executor, private GroundedResponseFormatter $formatter) {}

    public function customer(Customer $customer): array
    {
        $this->ensureConfigured();

        return $this->write('Summarize this customer in five sections: Customer overview, Relationship status, Associated deals, Potential opportunities, Notes and risks. Mention missing information explicitly.', $this->contexts->customer($customer));
    }

    public function report(User $user): array
    {
        $this->ensureConfigured();

        return $this->write('Summarize sales performance, stage concentration, potential risks, and suggested next steps. This is a current snapshot, not a historical trend. Expected close dates are not actual revenue.', $this->contexts->salesReport($this->reports->generate($user)));
    }

    public function ask(User $user, string $question): array
    {
        $this->ensureConfigured();
        $question = $this->contexts->clean($question);
        $call = $this->provider->chooseTool($question, $this->tools());
        $name = $call['name'] ?? '';
        $arguments = $call['arguments'] ?? [];

        if ($name === 'unsupported_question') {
            $this->catalog->validate($name, $arguments);

            return $this->formatter->unsupported();
        }

        return $this->write('Answer the question using only this query result: '.$question, $this->executor->execute($user, $name, $arguments));
    }

    public function tools(): array
    {
        return $this->catalog->definitions();
    }

    private function write(string $task, array $context): array
    {
        $result = $this->provider->summarize($task, $this->contexts->publicContext($context));

        return $this->formatter->format($result, $context);
    }

    private function ensureConfigured(): void
    {
        if (! $this->provider->isConfigured()) {
            throw new AiUnavailable('AI is not configured. Add a valid API key on the backend to enable live answers.');
        }
    }
}
