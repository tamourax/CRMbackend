<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use App\Services\CrmQueries;
use App\Services\SalesReportService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AiAssistant
{
    public function __construct(private AiProviderInterface $provider, private CrmQueries $queries, private SalesReportService $reports, private ContextBuilder $contexts) {}

    public function customer(Customer $customer): array
    {
        $this->ensureConfigured();

        return $this->write('Summarize this customer in five sections: Customer overview, Relationship status, Associated deals, Potential opportunities, Notes and risks. Mention missing information explicitly.', $this->contexts->customer($customer));
    }

    public function report(User $user): array
    {
        $this->ensureConfigured();
        $report = $this->reports->generate($user);
        $facts = array_filter($report, fn ($value) => is_scalar($value));
        $render = $facts;
        foreach ($report['stages'] as $stage) {
            $render[$stage['stage'].'_count'] = $stage['count'];
            $render[$stage['stage'].'_value'] = $stage['value'];
        }
        $customers = [];
        $sources = ['sales_report' => ['type' => 'aggregate', 'label' => 'Sales report · all your deals']];
        foreach ($report['top_customers'] as $customer) {
            $key = 'customer_'.$customer['id'];
            $customers[] = ['source_key' => $key, 'company' => $this->contexts->clean($customer['company'], [$customer['name']]), 'deal_count' => $customer['deal_count'], 'total_value' => $customer['total_value']];
            $sources[$key] = ['type' => 'customer', 'id' => $customer['id'], 'label' => $customer['company']];
            $render[$key.'_value'] = $customer['total_value'];
            $render[$key.'_count'] = $customer['deal_count'];
            $render[$key.'_company'] = $customer['company'];
        }

        return $this->write('Summarize sales performance, stage concentration, potential risks, and suggested next steps. This is a current snapshot, not a historical trend. Expected close dates are not actual revenue.', [
            'facts' => $facts, 'render_values' => $render, 'stages' => $report['stages'], 'top_customers' => $customers,
            'sources' => $sources, 'limitations' => ['No historical stage changes or actual closing dates are stored; growth trends and realized revenue cannot be determined.'],
        ]);
    }

    public function ask(User $user, string $question): array
    {
        $this->ensureConfigured();
        $call = $this->provider->chooseTool($this->contexts->clean($question), $this->tools());
        $name = $call['name'] ?? '';
        $args = $call['arguments'] ?? [];
        $this->validateTool($name, $args);
        if ($name === 'unsupported_question') {
            return ['sections' => [['title' => 'Available information', 'content' => 'I can answer questions about your accessible deals, closing dates, sales stages, customer rankings, and recorded loss reasons. Please ask a specific question in that scope. Historical changes and other users’ records are unavailable.']], 'facts' => (object) [], 'sources' => [], 'generated_at' => now()->toIso8601String(), 'mode' => 'live', 'limitations' => ['No database query was executed for this unsupported question.']];
        }
        $context = match ($name) {
            'search_deals' => $this->dealSearch($user, $args),
            'stage_totals' => $this->stageTotals($user, $args['stage']),
            'top_customers' => $this->customerRanking($user, $args),
            'lost_deal_reasons' => $this->lossReasons($user),
        };

        return $this->write('Answer the question using only this query result: '.$this->contexts->clean($question), $context);
    }

    public function tools(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableId = ['type' => ['integer', 'null']];

        return [
            $this->tool('search_deals', 'Find accessible deals by stage, expected close dates, title, customer, or explicit deal ID. All database filtering is performed by the server.', [
                'stage' => ['type' => ['string', 'null'], 'enum' => [...Deal::STAGES, 'open', null]],
                'closing' => ['type' => ['string', 'null'], 'enum' => ['this_month', 'overdue', null]],
                'customer_id' => $nullableId, 'deal_id' => $nullableId,
                'search' => $nullableString, 'close_from' => $nullableString, 'close_to' => $nullableString,
                'sort' => ['type' => 'string', 'enum' => ['value_desc', 'value_asc', 'close_asc', 'newest']],
                'limit' => ['type' => 'integer'],
            ]),
            $this->tool('stage_totals', 'Calculate the total count and value across all accessible deals in a sales stage, or open stages.', ['stage' => ['type' => 'string', 'enum' => [...Deal::STAGES, 'open']]]),
            $this->tool('top_customers', 'Rank customers by their active deal count or by total value across all deal stages.', ['metric' => ['type' => 'string', 'enum' => ['active_count', 'total_value']], 'limit' => ['type' => 'integer']]),
            $this->tool('lost_deal_reasons', 'Retrieve explicitly recorded lost deal reasons and count missing reasons. Never infer reasons.', []),
            $this->tool('unsupported_question', 'Use for unavailable data, ambiguous requests needing clarification, requests for other users, or anything outside these read-only capabilities.', []),
        ];
    }

    private function tool(string $name, string $description, array $properties): array
    {
        return ['type' => 'function', 'name' => $name, 'description' => $description, 'strict' => true, 'parameters' => ['type' => 'object', 'properties' => $properties ?: (object) [], 'required' => array_keys($properties), 'additionalProperties' => false]];
    }

    private function validateTool(string $name, mixed $arguments): void
    {
        $rules = match ($name) {
            'search_deals' => [
                'stage' => ['nullable', Rule::in([...Deal::STAGES, 'open'])], 'closing' => ['nullable', Rule::in(['this_month', 'overdue'])],
                'customer_id' => ['nullable', 'integer', 'min:1'], 'deal_id' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:200'],
                'close_from' => ['nullable', 'date_format:Y-m-d'], 'close_to' => ['nullable', 'date_format:Y-m-d'],
                'sort' => ['required', Rule::in(['value_desc', 'value_asc', 'close_asc', 'newest'])], 'limit' => ['required', 'integer', 'between:1,20'],
            ],
            'stage_totals' => ['stage' => ['required', Rule::in([...Deal::STAGES, 'open'])]],
            'top_customers' => ['metric' => ['required', Rule::in(['active_count', 'total_value'])], 'limit' => ['required', 'integer', 'between:1,20']],
            'lost_deal_reasons', 'unsupported_question' => [],
            default => null,
        };
        if ($rules === null || ! is_array($arguments) || array_diff(array_keys($arguments), array_keys($rules)) || Validator::make($arguments, $rules)->fails()) {
            throw new AiUnavailable('The assistant selected an invalid query. Please rephrase your question.');
        }
        if (! empty($arguments['close_from']) && ! empty($arguments['close_to']) && $arguments['close_to'] < $arguments['close_from']) {
            throw new AiUnavailable('The assistant selected an invalid date range. Please rephrase your question.');
        }
    }

    private function dealSearch(User $user, array $args): array
    {
        $query = $this->queries->deals($user, $args);
        $count = (clone $query)->count();
        $facts = ['matched_count' => $count, 'displayed_count' => min($count, $args['limit']), 'total_value' => CrmQueries::decimal((clone $query)->sum('value'))];
        $records = $this->queries->sortDeals($query->with('customer'), $args['sort'])->limit($args['limit'])->get();

        return $this->recordsContext($records->all(), $facts, $count > $args['limit']);
    }

    private function stageTotals(User $user, string $stage): array
    {
        $query = $this->queries->deals($user, ['stage' => $stage]);
        $facts = ['stage' => $stage, 'deal_count' => (clone $query)->count(), 'total_value' => CrmQueries::decimal((clone $query)->sum('value'))];

        return ['facts' => $facts, 'render_values' => $facts, 'sources' => ['stage_total' => ['type' => 'aggregate', 'label' => ucfirst($stage).' · all matching deals']], 'limitations' => []];
    }

    private function customerRanking(User $user, array $args): array
    {
        $customers = $this->reports->topCustomers($user, $args['metric'], $args['limit']);
        $facts = ['displayed_customers' => count($customers), 'ranking_metric' => $args['metric']];
        $render = $facts;
        $sources = [];
        $records = [];
        foreach ($customers as $customer) {
            $key = 'customer_'.$customer['id'];
            $sources[$key] = ['type' => 'customer', 'id' => $customer['id'], 'label' => $customer['company']];
            $render[$key.'_company'] = $customer['company'];
            $render[$key.'_count'] = $customer['deal_count'];
            $render[$key.'_value'] = $customer['total_value'];
            $records[] = ['source_key' => $key, 'company' => $this->contexts->clean($customer['company'], [$customer['name']]), 'deal_count' => $customer['deal_count'], 'total_value' => $customer['total_value']];
        }

        return ['facts' => $facts, 'render_values' => $render, 'records' => $records, 'sources' => $sources, 'limitations' => ['This is a limited ranking, not a list of all customers.']];
    }

    private function lossReasons(User $user): array
    {
        $query = $this->queries->deals($user, ['stage' => 'lost']);
        $count = (clone $query)->count();
        $recorded = (clone $query)->whereNotNull('lost_reason')->whereRaw("TRIM(lost_reason) <> ''")->count();
        $records = (clone $query)->with('customer')->orderByDesc('value')->orderBy('id')->limit(20)->get();

        return $this->recordsContext($records->all(), ['lost_deals' => $count, 'recorded_reason_count' => $recorded, 'unrecorded_reason_count' => $count - $recorded], $count > 20);
    }

    private function recordsContext(array $deals, array $facts, bool $truncated): array
    {
        $sources = ['query_result' => ['type' => 'aggregate', 'label' => 'Authorized CRM query · all matching records']];
        $render = $facts;
        $records = [];
        foreach ($deals as $deal) {
            $key = 'deal_'.$deal->id;
            $sources[$key] = ['type' => 'deal', 'id' => $deal->id, 'label' => $deal->title];
            $render[$key.'_title'] = $deal->title;
            $render[$key.'_value'] = $deal->value;
            $render[$key.'_close_date'] = $deal->expected_close_date?->toDateString() ?? 'Not scheduled';
            $records[] = $this->contexts->deal($deal);
        }

        return ['facts' => $facts, 'render_values' => $render, 'records' => $records, 'sources' => $sources, 'limitations' => $truncated ? ['Record details are limited to the highest-ranked results; aggregate facts cover all matching records.'] : []];
    }

    private function write(string $task, array $context): array
    {
        $result = $this->provider->summarize($task, $this->contexts->publicContext($context));
        $validator = Validator::make($result, ['sections' => ['required', 'array', 'min:1', 'max:8'], 'sections.*.title' => ['required', 'string', 'max:120'], 'sections.*.content' => ['required', 'string', 'max:4000'], 'source_keys' => ['present', 'array', 'max:30'], 'source_keys.*' => ['string', Rule::in(array_keys($context['sources']))]]);
        if ($validator->fails() || (count($context['sources']) > 0 && empty($result['source_keys']))) {
            throw new AiUnavailable('The AI response could not be verified against its sources. Please try again.');
        }
        $sections = [];
        foreach ($result['sections'] as $section) {
            $content = $section['content'];
            preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $content, $matches);
            if (array_diff($matches[1], array_keys($context['render_values']))) {
                throw new AiUnavailable('The AI response contained an unsupported fact. Please try again.');
            }
            $untemplated = preg_replace('/\{\{[a-zA-Z0-9_]+\}\}/', '', $content);
            if (preg_match('/[0-9{}]/', $untemplated.$section['title'])) {
                throw new AiUnavailable('The AI response contained an unverified number. Please try again.');
            }
            $content = preg_replace_callback('/\{\{([a-zA-Z0-9_]+)\}\}/', function (array $match) use ($context): string {
                $value = (string) $context['render_values'][$match[1]];

                return str_contains($match[1], 'value') && is_numeric($value) ? 'USD '.$value : $value;
            }, $content);
            $sections[] = ['title' => $section['title'], 'content' => $content];
        }

        return ['sections' => $sections, 'facts' => $context['facts'], 'sources' => array_values(array_intersect_key($context['sources'], array_flip($result['source_keys']))), 'generated_at' => now()->toIso8601String(), 'mode' => 'live', 'limitations' => $context['limitations']];
    }

    private function ensureConfigured(): void
    {
        if (! $this->provider->isConfigured()) {
            throw new AiUnavailable('AI is not configured. Add a valid API key on the backend to enable live answers.');
        }
    }
}
