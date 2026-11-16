<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Deal;
use App\Services\CrmQueries;

class ContextBuilder
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
            'source_key' => 'deal_'.$deal->id, 'title' => $this->clean($deal->title, $sensitive),
            'company' => $this->clean($deal->customer->company, $sensitive),
            'value' => $deal->value, 'stage' => $deal->stage,
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
            'description' => $this->clean($deal->description, $sensitive),
            'lost_reason' => $this->clean($deal->lost_reason, $sensitive),
        ];
    }

    public function customer(Customer $customer): array
    {
        $base = $customer->deals();
        $records = (clone $base)->with('customer')->orderByDesc('value')->orderBy('id')->limit(20)->get();
        $facts = ['total_deals' => (clone $base)->count(), 'open_deals' => (clone $base)->open()->count(), 'pipeline_value' => CrmQueries::decimal((clone $base)->open()->sum('value')), 'won_value' => CrmQueries::decimal((clone $base)->where('stage', 'won')->sum('value'))];
        $sources = ['customer_'.$customer->id => ['type' => 'customer', 'id' => $customer->id, 'label' => $customer->company]];
        $render = $facts;
        foreach ($records as $deal) {
            $sources['deal_'.$deal->id] = ['type' => 'deal', 'id' => $deal->id, 'label' => $deal->title];
            $render['deal_'.$deal->id.'_title'] = $deal->title;
            $render['deal_'.$deal->id.'_value'] = $deal->value;
            $render['deal_'.$deal->id.'_close_date'] = $deal->expected_close_date?->toDateString() ?? 'Not scheduled';
        }
        return [
            'facts' => $facts, 'sources' => $sources, 'render_values' => $render,
            'records' => $records->map(fn (Deal $deal) => $this->deal($deal))->all(),
            'customer' => ['company' => $this->clean($customer->company, [$customer->name]), 'industry' => $customer->industry, 'status' => $customer->status, 'notes' => $this->clean($customer->notes, [$customer->name, $customer->email, $customer->phone])],
            'limitations' => $facts['total_deals'] > 20 ? ['Only the twenty highest-value deals are included in the narrative; aggregates cover all associated deals.'] : [],
        ];
    }

    public function publicContext(array $context): array
    {
        $public = $context;
        $public['source_keys'] = array_keys($context['sources']);
        unset($public['sources']);
        foreach ($public['render_values'] as $key => $value) {
            $public['render_values'][$key] = is_string($value) ? $this->clean($value) : $value;
        }
        return $public;
    }
}
