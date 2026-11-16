<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'title' => $this->localized('title'),
            'value' => $this->value,
            'stage' => $this->stage,
            'stage_label' => __('crm.stage.'.$this->stage),
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'description' => $this->localized('description'),
            'lost_reason' => $this->localized('lost_reason'),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->localized('name'),
                'company' => $this->customer->localized('company'),
                'industry' => $this->customer->localized('industry'),
                'city' => $this->customer->localized('city'),
            ]),
        ];
    }
}
