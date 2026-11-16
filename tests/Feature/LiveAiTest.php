<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

#[Group('live')]
class LiveAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_LIVE_AI') !== '1') {
            $this->markTestSkipped('Opt-in only: set RUN_LIVE_AI=1 to use the ignored local provider key.');
        }
        $local = Dotenv::parse(file_get_contents(base_path('.env')));
        config([
            'ai.provider' => 'gemini', 'ai.gemini_api_key' => $local['GEMINI_API_KEY'] ?? null,
            'ai.gemini_model' => $local['GEMINI_MODEL'] ?? 'gemini-3.5-flash-lite',
            'ai.ca_bundle' => $local['AI_CA_BUNDLE'] ?? null,
        ]);
        if (getenv('LIVE_AI_DIAGNOSTICS') === '1') {
            $key = (string) config('ai.gemini_api_key');
            Http::globalResponseMiddleware(function (ResponseInterface $response) use ($key): ResponseInterface {
                if ($response->getStatusCode() >= 400) {
                    $error = json_decode((string) $response->getBody(), true)['error'] ?? [];
                    fwrite(STDERR, json_encode([
                        'provider_http_status' => $response->getStatusCode(),
                        'provider_status' => $error['status'] ?? null,
                        'message' => mb_substr(str_replace($key, '[redacted]', $error['message'] ?? ''), 0, 500),
                    ]).PHP_EOL);
                }

                return $response;
            });
        }
    }

    private function fixture(): Customer
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create(['company' => 'Synthetic Test Industries', 'notes' => 'Procurement is reviewing the proposal. Budget approval is pending.']);
        foreach ([['lead', '1000.00'], ['negotiation', '2000.00'], ['proposal', '3000.00'], ['won', '500.00'], ['lost', '700.00']] as [$stage, $value]) {
            Deal::factory()->for($customer)->create(['title' => ucfirst($stage).' opportunity', 'stage' => $stage, 'value' => $value, 'expected_close_date' => now()->endOfMonth()->toDateString(), 'lost_reason' => $stage === 'lost' ? 'Budget was frozen.' : null]);
        }
        Deal::factory()->create(['value' => '999999.00', 'stage' => 'negotiation']);
        $this->actingAs($user);

        return $customer;
    }

    public function test_provider_can_complete_a_minimal_request(): void
    {
        $response = Http::withHeaders(['x-goog-api-key' => config('ai.gemini_api_key')])
            ->withOptions(['verify' => config('ai.ca_bundle') ?: true])
            ->connectTimeout(5)->timeout(25)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('ai.gemini_model').':generateContent', [
                'contents' => [['parts' => [['text' => 'Reply with only OK.']]]],
                'generationConfig' => ['maxOutputTokens' => 64, 'thinkingConfig' => ['thinkingLevel' => 'minimal']],
            ]);
        $message = str_replace((string) config('ai.gemini_api_key'), '[redacted]', (string) $response->json('error.message'));
        $this->assertSame(200, $response->status(), $message);
    }

    public function test_real_provider_answers_the_five_assessment_questions(): void
    {
        $this->fixture();
        $cases = [
            ['What are the highest-value open deals?', 'matched_count', 3],
            ['Which deals are expected to close this month?', 'matched_count', 5],
            ['What is the total value of deals in the Negotiation stage?', 'total_value', '2000.00'],
            ['Which customers have the most active deals?', 'displayed_customers', 1],
            ['What are the reasons behind the lost deals?', 'recorded_reason_count', 1],
        ];
        foreach ($cases as [$question, $fact, $expected]) {
            $response = $this->postJson('/api/assistant/messages', compact('question'));
            $this->assertSame(200, $response->status(), $question.' — '.$response->json('message'));
            $response->assertJsonPath('data.facts.'.$fact, $expected)->assertJsonPath('data.mode', 'live');
        }
    }

    public function test_real_customer_summary_has_all_five_sections(): void
    {
        $customer = $this->fixture();
        $response = $this->postJson('/api/customers/'.$customer->id.'/ai-summary');
        $this->assertSame(200, $response->status(), (string) $response->json('message'));
        $response->assertJsonPath('data.facts.pipeline_value', '6000.00')->assertJsonCount(5, 'data.sections');
    }

    public function test_real_sales_report_summary_matches_database(): void
    {
        $this->fixture();
        $response = $this->postJson('/api/reports/sales/ai-summary');
        $this->assertSame(200, $response->status(), (string) $response->json('message'));
        $response->assertJsonPath('data.facts.total_value', '7200.00')->assertJsonPath('data.facts.pipeline_value', '6000.00');
    }
}
