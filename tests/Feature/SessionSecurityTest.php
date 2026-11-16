<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_login_revokes_previous_device_even_if_its_session_survives(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $credentials = ['email' => $user->email, 'password' => 'correct-password'];
        $this->postJson('/login', $credentials)->assertOk();
        $old = session()->all();
        $oldHash = $user->fresh()->auth_session_hash;
        $this->postJson('/login', $credentials)->assertOk();
        $this->assertNotSame($oldHash, $user->fresh()->auth_session_hash);
        $this->app['auth']->forgetGuards();
        $this->withSession($old)->getJson('/api/user', ['Origin' => 'http://localhost:5173'])->assertUnauthorized();
    }

    public function test_absolute_expiry_cannot_be_extended_by_refresh(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertOk();
        $started = session('authenticated_at');
        $oldId = session()->getId();
        $this->postJson('/session/refresh')->assertOk()->assertJsonMissingPath('data.auth_session_hash');
        $this->assertNotSame($oldId, session()->getId());
        $this->assertSame($started, session('authenticated_at'));
        $this->travel(config('session.absolute_lifetime'))->minutes();
        $this->postJson('/session/refresh')->assertUnauthorized();
    }

    public function test_authenticated_legacy_session_without_device_secret_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([Auth::guard('web')->getName() => $user->id])
            ->getJson('/api/user', ['Origin' => 'http://localhost:5173'])->assertUnauthorized();
    }

    public function test_logout_revokes_server_side_secret(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertOk();
        $this->assertNotNull($user->fresh()->auth_session_hash);
        $this->postJson('/logout')->assertOk();
        $this->assertNull($user->fresh()->auth_session_hash);
    }

    public function test_refresh_rotates_secret_and_rejects_replayed_old_session(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertOk();
        $oldSession = session()->all();
        $oldHash = $user->fresh()->auth_session_hash;
        $this->postJson('/session/refresh')->assertOk();
        $this->assertNotSame($oldHash, $user->fresh()->auth_session_hash);
        $this->app['auth']->forgetGuards();
        $this->withSession($oldSession)->postJson('/session/refresh')->assertUnauthorized();
    }

    public function test_api_responses_are_private_and_cannot_be_embedded(): void
    {
        $this->getJson('/api/user')->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $this->getJson('/')->headers->get('Cache-Control'));
    }

    public function test_refresh_requires_authentication(): void
    {
        $this->postJson('/session/refresh')->assertUnauthorized();
    }

    public function test_login_and_refresh_reject_requests_without_csrf_protection(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->postJson('/login', ['email' => 'test@example.com', 'password' => 'invalid'])->assertStatus(419);
        $this->postJson('/session/refresh')->assertStatus(419);
    }
}
