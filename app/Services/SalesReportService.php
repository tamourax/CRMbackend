<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SalesReportService
{
    public function __construct(private CrmQueries $queries) {}

    public function topCustomers(User $user, string $metric = 'total_value', int $limit = 5): array
    {
        $query = $this->queries->customers($user)->withCount(['deals' => function (Builder $query) use ($metric): void {
            if ($metric === 'active_count') {
                $query->open();
            }
        }])->withSum(['deals' => function (Builder $query) use ($metric): void {
            if ($metric === 'active_count') {
                $query->open();
            }
        }], 'value');
        $query->whereHas('deals', function (Builder $query) use ($metric): void {
            if ($metric === 'active_count') {
                $query->open();
            }
        });
        $query->orderByDesc($metric === 'active_count' ? 'deals_count' : 'deals_sum_value')->orderBy('id');

        return $query->limit($limit)->get()->map(fn ($customer) => [
            'id' => $customer->id, 'company' => $customer->company, 'name' => $customer->name,
            'deal_count' => $customer->deals_count, 'total_value' => CrmQueries::decimal($customer->deals_sum_value ?? 0),
        ])->all();
    }

    public function generate(User $user): array
    {
        $base = $this->queries->deals($user);
        $grouped = (clone $base)->selectRaw('stage, COUNT(*) AS deal_count, SUM(value) AS total_value')->groupBy('stage')->get()->keyBy('stage');
        $stages = array_map(fn (string $stage) => [
            'stage' => $stage, 'count' => (int) ($grouped->get($stage)?->deal_count ?? 0),
            'value' => CrmQueries::decimal($grouped->get($stage)?->total_value ?? 0),
        ], Deal::STAGES);
        $won = (clone $base)->where('stage', 'won');
        $lost = (clone $base)->where('stage', 'lost');
        $closing = $this->queries->deals($user, ['stage' => 'open', 'closing' => 'this_month']);

        return [
            'total_deals' => (clone $base)->count(), 'total_value' => CrmQueries::decimal((clone $base)->sum('value')),
            'pipeline_value' => CrmQueries::decimal((clone $base)->open()->sum('value')), 'open_count' => (clone $base)->open()->count(),
            'won_count' => (clone $won)->count(), 'won_value' => CrmQueries::decimal((clone $won)->sum('value')),
            'lost_count' => (clone $lost)->count(), 'lost_value' => CrmQueries::decimal((clone $lost)->sum('value')),
            'closing_this_month_count' => (clone $closing)->count(), 'closing_this_month_value' => CrmQueries::decimal((clone $closing)->sum('value')),
            'overdue_count' => $this->queries->deals($user, ['closing' => 'overdue'])->count(),
            'stages' => $stages, 'top_customers' => $this->topCustomers($user),
            'as_of' => now()->toIso8601String(), 'timezone' => config('app.timezone'), 'currency' => 'USD',
        ];
    }
}
