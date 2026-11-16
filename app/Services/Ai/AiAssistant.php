<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\User;
use App\Services\SalesReportService;

class AiAssistant
{
    public function __construct(private AiProviderInterface $provider, private SalesReportService $reports, private AiContextBuilder $contexts, private DealToolCatalog $catalog, private DealToolExecutor $executor, private GroundedResponseFormatter $formatter) {}

    public function customer(Customer $customer): array
    {
        $this->ensureConfigured();

        return $this->write(__('crm.ai.customer_task'), $this->contexts->customer($customer));
    }

    public function report(User $user): array
    {
        $this->ensureConfigured();

        return $this->write(__('crm.ai.report_task'), $this->contexts->salesReport($this->reports->generate($user)));
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

        return $this->write('Answer the question using only this query result: ' . $question, $this->executor->execute($user, $name, $arguments));
    }

    public function tools(): array
    {
        return $this->catalog->definitions();
    }

    private function write(string $task, array $context): array
    {
        $result = $this->provider->summarize($task, $this->contexts->publicContext($context));

        try {
            return $this->formatter->format($result, $context);
        } catch (AiVerificationFailed) {
            $result = $this->provider->summarize(
                $task . ' The previous response failed validation. Generate a fresh response using ONLY exact keys from render_values and source_keys. Omit any claim without a supplied key. Never write literal numbers, dates, or braces except valid placeholders. Do not invent placeholder names.',
                $this->contexts->publicContext($context),
            );

            return $this->formatter->format($result, $context);
        }
    }

    private function ensureConfigured(): void
    {
        if (! $this->provider->isConfigured()) {
            throw new AiUnavailable(__('crm.ai.not_configured'));
        }
    }
}
