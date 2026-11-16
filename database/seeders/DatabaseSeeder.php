<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is only allowed in local or testing environments.');
        }
        $companies = [
            ['Atlas Industries', 'Manufacturing', 'Morgan Reed'], ['Nova Health', 'Healthcare', 'Jamie Chen'],
            ['Meridian Studio', 'Design', 'Taylor Brooks'], ['Evergreen Energy', 'Energy', 'Jordan Ellis'],
            ['Forma Technologies', 'Technology', 'Casey Morgan'], ['Northstar Logistics', 'Logistics', 'Sam Patel'],
            ['Bloom Retail', 'Retail', 'Riley Park'], ['Cobalt Finance', 'Financial Services', 'Avery Lane'],
            ['Lumina Education', 'Education', 'Robin Wells'], ['Horizon Labs', 'Research', 'Cameron Blake'],
        ];
        $titles = ['Platform rollout', 'Annual service renewal', 'Enterprise expansion', 'Team onboarding', 'Analytics pilot', 'Support partnership'];
        $notes = [
            'Interested in a wider rollout. Procurement requires a security review before signing.',
            'Strong engagement from the operations team. Budget approval is still pending.',
            'Existing relationship is positive. The customer requested a phased onboarding plan.',
            'The decision maker has asked for a clear implementation timeline. Follow up after the proposal review.',
            null,
        ];
        $lossReasons = ['Budget was reassigned to a different initiative.', 'Selected an incumbent vendor with an existing contract.', 'Implementation timeline did not match their planned launch.', null];
        foreach ([['Alex Morgan', 'alex@orbit.example'], ['Sarah Chen', 'sarah@orbit.example']] as $userIndex => [$name, $email]) {
            $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('OrbitDemo!2026')]);
            foreach ($companies as $index => [$company, $industry, $contact]) {
                $companyName = $userIndex === 0 ? $company : $company.' Partners';
                $customer = Customer::updateOrCreate(['user_id' => $user->id, 'company' => $companyName], [
                    'name' => $contact, 'email' => 'contact'.($index + 1).'@customer'.($userIndex + 1).'.example',
                    'phone' => '+1 202 555 '.str_pad((string) ($index + 100), 4, '0', STR_PAD_LEFT),
                    'industry' => $industry, 'status' => ['active', 'prospect', 'active', 'inactive'][$index % 4], 'notes' => $notes[$index % 5],
                ]);
                $dealCount = $index === 9 ? 0 : ($index === 0 ? 6 : 3);
                for ($dealIndex = 0; $dealIndex < $dealCount; $dealIndex++) {
                    $sequence = $index * 3 + $dealIndex;
                    $stage = Deal::STAGES[($sequence + $userIndex) % 6];
                    $date = match ($sequence % 5) {
                        0 => now()->startOfMonth()->addDays(4),
                        1 => now()->endOfMonth(),
                        2 => now()->addMonthNoOverflow()->startOfMonth()->addDays(8),
                        3 => now()->subMonthNoOverflow()->startOfMonth()->addDays(12),
                        default => now()->addDays(3),
                    };
                    Deal::updateOrCreate(['customer_id' => $customer->id, 'title' => $titles[$sequence % 6].' · '.strtok($company, ' ')], [
                        'value' => (string) (8500 + (($sequence * 13750 + $userIndex * 5000) % 142000)),
                        'stage' => $stage, 'expected_close_date' => $sequence % 13 === 0 ? null : $date->toDateString(),
                        'description' => 'A '.$industry.' opportunity covering discovery, implementation, and ongoing customer support. Scope and commercial terms are documented for review.',
                        'lost_reason' => $stage === 'lost' ? $lossReasons[$index % 4] : null,
                    ]);
                }
            }
        }
    }
}
