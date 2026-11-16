<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrmApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_crm_endpoints_require_authentication(): void
    {
        foreach (['/api/user', '/api/customers', '/api/customers/1', '/api/deals', '/api/deals/1', '/api/reports/sales', '/api/status'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        foreach (['/api/assistant/messages', '/api/customers/1/ai-summary', '/api/reports/sales/ai-summary'] as $path) {
            $this->postJson($path)->assertUnauthorized();
        }
    }

    public function test_login_validation_authentication_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'test@orbit.example', 'password' => Hash::make('correct-password')]);
        $this->postJson('/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
        $this->getJson('/api/user')->assertOk();
        $this->postJson('/logout')->assertOk();
        $this->assertGuest('web');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'limited@orbit.example', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'limited@orbit.example', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_customers_and_deals_are_scoped_and_relationships_are_correct(): void
    {
        $user = User::factory()->create();
        $own = Customer::factory()->for($user)->create();
        $foreign = Customer::factory()->create();
        $ownDeal = Deal::factory()->for($own)->create();
        $foreignDeal = Deal::factory()->for($foreign)->create(['value' => '999999.00']);
        $this->actingAs($user)->getJson('/api/customers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.deals_count', 1);
        $this->getJson('/api/customers/'.$own->id)->assertOk()->assertJsonPath('data.deals.0.id', $ownDeal->id);
        $this->getJson('/api/deals')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer.id', $own->id);
        $this->getJson('/api/deals/'.$ownDeal->id)->assertOk()->assertJsonPath('data.customer.company', $own->company);
        $this->getJson('/api/customers/'.$foreign->id)->assertNotFound();
        $this->getJson('/api/deals/'.$foreignDeal->id)->assertNotFound();
        $this->postJson('/api/customers/'.$foreign->id.'/ai-summary')->assertNotFound();
        $this->getJson('/api/deals?customer_id='.$foreign->id)->assertJsonCount(0, 'data');
        $this->assertTrue(Gate::forUser($user)->allows('view', $own));
        $this->assertFalse(Gate::forUser($user)->allows('view', $foreignDeal));
    }

    public function test_sales_report_uses_exact_decimal_database_calculations(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        foreach ([['lead', '1000.10'], ['negotiation', '2000.20'], ['proposal', '3000.30'], ['won', '500.40'], ['lost', '700.50']] as [$stage, $value]) {
            Deal::factory()->for($customer)->create(compact('stage', 'value'));
        }
        Deal::factory()->create(['value' => '990000.00']);
        $this->actingAs($user)->getJson('/api/reports/sales')->assertOk()
            ->assertJsonPath('data.total_deals', 5)->assertJsonPath('data.total_value', '7201.50')
            ->assertJsonPath('data.pipeline_value', '6000.60')->assertJsonPath('data.won_count', 1)
            ->assertJsonPath('data.won_value', '500.40')->assertJsonPath('data.lost_value', '700.50')
            ->assertJsonPath('data.stages.3.value', '2000.20')->assertJsonPath('data.top_customers.0.id', $customer->id)
            ->assertJsonPath('data.top_customers.0.total_value', '7201.50');
    }

    public function test_empty_report_and_customer_without_deals_are_supported(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create(['notes' => null]);
        $this->actingAs($user)->getJson('/api/reports/sales')->assertOk()->assertJsonPath('data.pipeline_value', '0.00')->assertJsonCount(6, 'data.stages')->assertJsonCount(0, 'data.top_customers');
        $this->getJson('/api/customers/'.$customer->id)->assertOk()->assertJsonCount(0, 'data.deals')->assertJsonPath('data.notes', null);
    }

    public function test_current_month_includes_both_boundaries_and_excludes_other_months(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-16 12:00:00', 'Africa/Cairo'));
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        foreach (['2026-11-01', '2026-11-30', '2026-10-31', '2026-12-01'] as $date) {
            Deal::factory()->for($customer)->create(['expected_close_date' => $date, 'stage' => 'proposal']);
        }
        Deal::factory()->for($customer)->create(['expected_close_date' => '2026-11-16', 'stage' => 'won']);
        $this->actingAs($user)->getJson('/api/deals?closing=this_month&stage=open')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/reports/sales')->assertJsonPath('data.closing_this_month_count', 2);
    }

    public function test_filters_sorting_pagination_and_literal_search(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create(['company' => 'Atlas 100%']);
        Deal::factory()->for($customer)->create(['title' => 'Enterprise pilot', 'stage' => 'negotiation', 'value' => '999.99']);
        Deal::factory()->for($customer)->create(['title' => 'Small pilot', 'stage' => 'negotiation', 'value' => '10.00']);
        $this->actingAs($user)->getJson('/api/deals?stage=negotiation&sort=value_desc&per_page=1')->assertOk()->assertJsonPath('data.0.value', '999.99')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/customers?search=%25')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/deals?search=nonexistent')->assertJsonCount(0, 'data');
    }

    public function test_query_and_chat_validation(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['stage=invalid', 'per_page=101', 'page=0', 'sort=value;DROP', 'close_from=yesterday', 'customer_id=-1'] as $query) {
            $this->getJson('/api/deals?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/customers?status=invalid')->assertUnprocessable();
        foreach (['', str_repeat('a', 2001), '   '] as $question) {
            $this->postJson('/api/assistant/messages', compact('question'))->assertUnprocessable();
        }
    }

    public function test_database_rejects_negative_value_invalid_stage_and_orphan_relationship(): void
    {
        $customer = Customer::factory()->create();
        foreach ([['value' => '-1.00'], ['stage' => 'invented'], ['customer_id' => 9999999]] as $attributes) {
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                Deal::factory()->for($customer)->create($attributes);
                $this->fail('Expected a database constraint violation.');
            } catch (\Illuminate\Database\QueryException) {
                $this->assertTrue(true);
            } finally {
                \Illuminate\Support\Facades\DB::rollBack();
            }
        }
    }
}
