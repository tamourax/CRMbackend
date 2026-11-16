<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class DealFactory extends Factory
{
    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'title' => 'Annual service agreement', 'value' => '12500.00', 'stage' => 'proposal', 'expected_close_date' => now()->addDays(10)->toDateString(), 'description' => 'Onboarding and ongoing support.', 'lost_reason' => null];
    }
}
