<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CrmQueryService
{
    public function customers(User $user, array $filters = []): Builder
    {
        $query = Customer::query()->ownedBy($user);
        if (! empty($filters['search'])) {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']).'%';
            $query->where(fn (Builder $q) => $q
                ->where('name', 'ilike', $search)
                ->orWhere('company', 'ilike', $search)
                ->orWhere('translations->ar->name', 'ilike', $search)
                ->orWhere('translations->ar->company', 'ilike', $search));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    public function deals(User $user, array $filters = []): Builder
    {
        $query = Deal::query()->ownedBy($user);
        if (! empty($filters['stage'])) {
            $filters['stage'] === 'open' ? $query->open() : $query->where('stage', $filters['stage']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['deal_id'])) {
            $query->whereKey($filters['deal_id']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']).'%';
            $query->where(fn (Builder $q) => $q->where('title', 'ilike', $search)->orWhere('translations->ar->title', 'ilike', $search));
        }
        if (($filters['closing'] ?? null) === 'this_month') {
            $query->whereBetween('expected_close_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        }
        if (($filters['closing'] ?? null) === 'overdue') {
            $query->open()->where('expected_close_date', '<', now()->toDateString());
        }
        if (! empty($filters['close_from'])) {
            $query->where('expected_close_date', '>=', $filters['close_from']);
        }
        if (! empty($filters['close_to'])) {
            $query->where('expected_close_date', '<=', $filters['close_to']);
        }

        return $query;
    }

    public function sortDeals(Builder $query, ?string $sort = 'value_desc'): Builder
    {
        return match ($sort) {
            'value_asc' => $query->orderBy('value')->orderBy('id'),
            'close_asc' => $query->orderByRaw('expected_close_date ASC NULLS LAST')->orderBy('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('value')->orderBy('id'),
        };
    }

    public static function decimal(string|int $value): string
    {
        $parts = explode('.', (string) $value);

        return $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');
    }
}
