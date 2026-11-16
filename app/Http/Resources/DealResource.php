<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'customer_id' => $this->customer_id, 'title' => $this->title, 'value' => $this->value, 'stage' => $this->stage, 'expected_close_date' => $this->expected_close_date?->toDateString(), 'description' => $this->description, 'lost_reason' => $this->lost_reason, 'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name, 'company' => $this->customer->company, 'industry' => $this->customer->industry, 'city' => $this->customer->city])];
    }
}
