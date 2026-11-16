<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->localized('name'),
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->localized('company'),
            'industry' => $this->localized('industry'),
            'city' => $this->localized('city'),
            'status' => $this->status,
            'status_label' => __('crm.status.'.$this->status),
            'notes' => $this->when($this->relationLoaded('deals'), $this->localized('notes')),
            'deals_count' => $this->whenCounted('deals'),
            'deals' => DealResource::collection($this->whenLoaded('deals')),
        ];
    }
}
