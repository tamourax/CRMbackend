<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.provider' => 'gemini', 'ai.gemini_api_key' => 'test-placeholder-not-a-real-key', 'ai.ca_bundle' => null]);
        Http::preventStrayRequests();
    }

    public function test_provider_selection_follows_configuration(): void
    {
        $this->assertInstanceOf(GeminiProvider::class, app(AiProviderInterface::class));
        config(['ai.provider' => 'openai']);
        $this->assertInstanceOf(OpenAiProvider::class, app(AiProviderInterface::class));
    }

    public function test_tools_use_google_header_and_preserve_bounded_json_schemas(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['functionCall' => ['name' => 'stage_totals', 'args' => ['stage' => 'negotiation']]]]]]]])]);
        $result = (new GeminiProvider)->chooseTool('Total in negotiation?', app(AiAssistant::class)->tools());
        $this->assertSame(['name' => 'stage_totals', 'arguments' => ['stage' => 'negotiation']], $result);
        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-placeholder-not-a-real-key')
            && ! str_contains($request->url(), 'key=')
            && $request['toolConfig']['functionCallingConfig']['mode'] === 'ANY'
            && $request['tools'][0]['functionDeclarations'][0]['parametersJsonSchema']['additionalProperties'] === false);
    }

    public function test_summary_uses_the_shared_schema_and_ignores_thought_parts(): void
    {
        $expected = ['sections' => [['title' => 'Overview', 'content' => 'Value: {{total_value}}.']], 'source_keys' => ['stage_total']];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['thought' => true, 'text' => 'Private intermediate text'], ['text' => json_encode($expected)]]]]]])]);
        $this->assertSame($expected, (new GeminiProvider)->summarize('Summarize', ['facts' => [], 'render_values' => ['total_value' => '1.00'], 'source_keys' => ['stage_total']]));
        Http::assertSent(fn (Request $request) => $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['generationConfig']['responseJsonSchema']['additionalProperties'] === false);
    }

    public function test_unconfigured_gemini_makes_no_external_request(): void
    {
        config(['ai.gemini_api_key' => null]);
        $this->actingAs(User::factory()->create())->getJson('/api/status')->assertJsonPath('data.ai_configured', false);
        $this->postJson('/api/assistant/messages', ['question' => 'Show my deals'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_auth_quota_network_and_incomplete_responses_are_safe_errors(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([
            Http::response(['error' => ['message' => 'Sensitive provider details']], 403),
            Http::response(['error' => ['message' => 'Quota details']], 429),
            Http::failedConnection(),
            Http::response(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => []]]]]),
            Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]),
            Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'No tool selected']]]]]]),
        ] as $response) {
            Http::fake(['generativelanguage.googleapis.com/*' => $response]);
            $result = $this->postJson('/api/assistant/messages', ['question' => 'Show my deals'])->assertStatus(503);
            $this->assertStringNotContainsString('Sensitive provider details', $result->getContent());
        }
    }

    public function test_parallel_calls_and_invalid_arguments_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([
            [['functionCall' => ['name' => 'stage_totals', 'args' => []]], ['functionCall' => ['name' => 'lost_deal_reasons', 'args' => []]]],
            [['functionCall' => ['name' => 'stage_totals', 'args' => 'invalid']]],
        ] as $parts) {
            Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => $parts]]]])]);
            $this->postJson('/api/assistant/messages', ['question' => 'Show my deals'])->assertStatus(503);
        }
    }

    public function test_malformed_summary_is_rejected(): void
    {
        $candidate = ['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{broken']]]];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [$candidate]])]);
        $this->expectException(AiUnavailable::class);
        (new GeminiProvider)->summarize('Summarize', []);
    }

    public function test_transient_server_error_is_retried_once(): void
    {
        $candidate = ['finishReason' => 'STOP', 'content' => ['parts' => [['functionCall' => ['name' => 'stage_totals', 'args' => ['stage' => 'won']]]]]];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()->push(['error' => 'temporary'], 503)->push(['candidates' => [$candidate]])]);
        $this->assertSame('stage_totals', (new GeminiProvider)->chooseTool('Won total?', app(AiAssistant::class)->tools())['name']);
        Http::assertSentCount(2);
    }

    public function test_quota_failure_is_not_retried(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)]);
        $this->actingAs(User::factory()->create())->postJson('/api/assistant/messages', ['question' => 'Show my deals'])->assertStatus(503);
        Http::assertSentCount(1);
    }
}
