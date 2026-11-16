<?php

namespace App\Http\Requests;

use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'], 'stage' => ['nullable', Rule::in([...Deal::STAGES, 'open'])],
            'sort' => ['nullable', Rule::in(['value_desc', 'value_asc', 'close_asc', 'newest'])], 'customer_id' => ['nullable', 'integer', 'min:1'],
            'closing' => ['nullable', Rule::in(['this_month', 'overdue'])], 'close_from' => ['nullable', 'date_format:Y-m-d'],
            'close_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('close_from'), ['after_or_equal:close_from'])], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
