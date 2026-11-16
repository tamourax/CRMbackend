<?php

namespace App\Services\Ai;

use App\Models\Deal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DealToolCatalog
{
    public function definitions(): array
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

    public function validate(string $name, mixed $arguments): void
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
            throw new AiUnavailable(__('crm.ai.invalid_query'));
        }
        if (! empty($arguments['close_from']) && ! empty($arguments['close_to']) && $arguments['close_to'] < $arguments['close_from']) {
            throw new AiUnavailable(__('crm.ai.invalid_date_range'));
        }
    }
}
