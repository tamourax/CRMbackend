<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\CrmQueries;
use App\Services\SalesReportService;

class DealToolExecutor
{
    public function __construct(private CrmQueries $queries, private SalesReportService $reports, private ContextBuilder $contexts, private DealToolCatalog $catalog) {}

    public function execute(User $user, string $name, mixed $arguments): array
    {
        $this->catalog->validate($name, $arguments);

        return match ($name) {
            'search_deals' => $this->dealSearch($user, $arguments),
            'stage_totals' => $this->stageTotals($user, $arguments['stage']),
            'top_customers' => $this->customerRanking($user, $arguments),
            'lost_deal_reasons' => $this->lossReasons($user),
            default => throw new AiUnavailable(__('crm.ai.query_unavailable')),
        };
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
        $stageLabel = __('crm.stage.'.$stage);
        $facts = ['stage' => $stage, 'stage_label' => $stageLabel, 'deal_count' => (clone $query)->count(), 'total_value' => CrmQueries::decimal((clone $query)->sum('value'))];

        return ['facts' => $facts, 'render_values' => $facts, 'sources' => ['stage_total' => ['type' => 'aggregate', 'label' => __('crm.ai.stage_total_source', ['stage' => $stageLabel])]], 'limitations' => []];
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

        return ['facts' => $facts, 'render_values' => $render, 'records' => $records, 'sources' => $sources, 'limitations' => [__('crm.ai.customer_ranking_limitation')]];
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
        $sources = ['query_result' => ['type' => 'aggregate', 'label' => __('crm.ai.authorized_query_source')]];
        $render = $facts;
        $records = [];
        foreach ($deals as $deal) {
            $key = 'deal_'.$deal->id;
            $sources[$key] = ['type' => 'deal', 'id' => $deal->id, 'label' => $deal->localized('title')];
            $render[$key.'_title'] = $deal->localized('title');
            $render[$key.'_value'] = $deal->value;
            $render[$key.'_close_date'] = $deal->expected_close_date?->toDateString() ?? __('crm.ai.unscheduled_date');
            $records[] = $this->contexts->deal($deal);
        }

        return ['facts' => $facts, 'render_values' => $render, 'records' => $records, 'sources' => $sources, 'limitations' => $truncated ? [__('crm.ai.limited_records')] : []];
    }
}
