<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, OpenAiProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.hash('sha256', strtolower((string) $request->input('email')))));
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
    }
}
