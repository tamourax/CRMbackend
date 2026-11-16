<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\DealToolExecutor;
use App\Services\Ai\GroundedResponseFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiServiceBoundariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_executor_enforces_ownership_without_a_controller_or_provider(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        Deal::factory()->for($customer)->create(['stage' => 'negotiation', 'value' => '10.25']);
        Deal::factory()->create(['stage' => 'negotiation', 'value' => '999999.00']);

        $context = app(DealToolExecutor::class)->execute($user, 'stage_totals', ['stage' => 'negotiation']);

        $this->assertSame(['stage' => 'negotiation', 'deal_count' => 1, 'total_value' => '10.25'], $context['facts']);
    }

    public function test_executor_validates_arguments_even_when_called_directly(): void
    {
        $user = User::factory()->create();
        $this->expectException(AiUnavailable::class);
        app(DealToolExecutor::class)->execute($user, 'stage_totals', ['stage' => 'won', 'user_id' => 999]);
    }

    public function test_response_formatter_preserves_exact_amounts_sources_and_limitations(): void
    {
        $facts = ['total_value' => '23456789012.34', 'deal_count' => 3];
        $sources = ['stage_total' => ['type' => 'aggregate', 'label' => 'All matching records']];
        $context = ['facts' => $facts, 'render_values' => $facts, 'sources' => $sources, 'limitations' => ['Current snapshot only.']];
        $result = ['sections' => [['title' => 'Total', 'content' => '{{total_value}} across {{deal_count}} deals.']], 'source_keys' => ['stage_total']];

        $response = app(GroundedResponseFormatter::class)->format($result, $context);

        $this->assertSame('EGP 23456789012.34 across 3 deals.', $response['sections'][0]['content']);
        $this->assertSame($facts, $response['facts']);
        $this->assertSame(array_values($sources), $response['sources']);
        $this->assertSame($context['limitations'], $response['limitations']);
    }
}
