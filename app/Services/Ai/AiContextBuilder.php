<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Deal;
use App\Services\CrmQueryService;

class AiContextBuilder
{
    public function clean(?string $text, array $sensitive = []): ?string
    {
        if ($text === null) {
            return null;
        }
        foreach (array_filter($sensitive) as $value) {
            $text = str_ireplace($value, '[contact withheld]', $text);
        }
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email withheld]', $text);
        $text = preg_replace('/(?<!\w)\+?\d[\d\s().\-]{6,}\d(?!\w)/', '[number withheld]', $text);

        return mb_substr(strip_tags($text), 0, 2000);
    }

    public function deal(Deal $deal): array
    {
        $sensitive = [$deal->customer->name, $deal->customer->email, $deal->customer->phone];

        return [
            'source_key' => 'deal_'.$deal->id, 'title' => $this->clean($deal->localized('title'), $sensitive),
            'company' => $this->clean($deal->customer->localized('company'), $sensitive),
            'value' => $deal->value, 'stage' => $deal->stage, 'stage_label' => __('crm.stage.'.$deal->stage),
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
            'description' => $this->clean($deal->localized('description'), $sensitive),
            'lost_reason' => $this->clean($deal->localized('lost_reason'), $sensitive),
        ];
    }

    public function customer(Customer $customer): array
    {
        $base = $customer->deals();
        $records = (clone $base)->with('customer')->orderByDesc('value')->orderBy('id')->limit(20)->get();
        $facts = ['total_deals' => (clone $base)->count(), 'open_deals' => (clone $base)->open()->count(), 'pipeline_value' => CrmQueryService::decimal((clone $base)->open()->sum('value')), 'won_value' => CrmQueryService::decimal((clone $base)->where('stage', 'won')->sum('value'))];
        $sources = ['customer_'.$customer->id => ['type' => 'customer', 'id' => $customer->id, 'label' => $customer->localized('company')]];
        $render = $facts;
        foreach ($records as $deal) {
            $sources['deal_'.$deal->id] = ['type' => 'deal', 'id' => $deal->id, 'label' => $deal->localized('title')];
            $render['deal_'.$deal->id.'_title'] = $deal->localized('title');
            $render['deal_'.$deal->id.'_value'] = $deal->value;
            $render['deal_'.$deal->id.'_close_date'] = $deal->expected_close_date?->toDateString() ?? __('crm.ai.unscheduled_date');
        }

        return [
            'facts' => $facts, 'sources' => $sources, 'render_values' => $render,
            'records' => $records->map(fn (Deal $deal) => $this->deal($deal))->all(),
            'customer' => [
                'company' => $this->clean($customer->localized('company'), [$customer->localized('name')]),
                'industry' => $customer->localized('industry'),
                'city' => $this->clean($customer->localized('city')),
                'status' => $customer->status,
                'status_label' => __('crm.status.'.$customer->status),
                'notes' => $this->clean($customer->localized('notes'), [$customer->localized('name'), $customer->email, $customer->phone]),
            ],
            'limitations' => $facts['total_deals'] > 20 ? [__('crm.ai.customer_deal_limit', ['limit' => 20])] : [],
        ];
    }

    public function salesReport(array $report): array
    {
        $facts = array_filter($report, fn ($value) => is_scalar($value));
        $render = $facts;
        foreach ($report['stages'] as $stage) {
            $render[$stage['stage'].'_count'] = $stage['count'];
            $render[$stage['stage'].'_value'] = $stage['value'];
        }
        $customers = [];
        $sources = ['sales_report' => ['type' => 'aggregate', 'label' => __('crm.ai.sales_report_source')]];
        foreach ($report['top_customers'] as $customer) {
            $key = 'customer_'.$customer['id'];
            $customers[] = ['source_key' => $key, 'company' => $this->clean($customer['company'], [$customer['name']]), 'deal_count' => $customer['deal_count'], 'total_value' => $customer['total_value']];
            $sources[$key] = ['type' => 'customer', 'id' => $customer['id'], 'label' => $customer['company']];
            $render[$key.'_value'] = $customer['total_value'];
            $render[$key.'_count'] = $customer['deal_count'];
            $render[$key.'_company'] = $customer['company'];
        }

        return [
            'facts' => $facts, 'render_values' => $render, 'stages' => $report['stages'], 'top_customers' => $customers,
            'sources' => $sources, 'limitations' => [__('crm.ai.report_snapshot_limit')],
        ];
    }

    public function publicContext(array $context): array
    {
        $public = $context;
        $public['source_keys'] = array_keys($context['sources']);
        unset($public['sources']);
        foreach ($public['render_values'] as $key => $value) {
            $public['render_values'][$key] = is_string($value) && ! is_numeric($value) && ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? $this->clean($value) : $value;
        }
        foreach ($context['records'] ?? [] as $record) {
            $key = $record['source_key'];
            if (isset($record['title'])) {
                $public['render_values'][$key.'_title'] = $record['title'];
            }
            if (isset($public['render_values'][$key.'_company'])) {
                $public['render_values'][$key.'_company'] = $record['company'];
            }
        }

        return $public;
    }
}
