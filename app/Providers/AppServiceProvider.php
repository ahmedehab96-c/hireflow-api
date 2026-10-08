<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // General API budget, per user when signed in, otherwise per IP. Dashboard pages make
        // ~10 requests each, so this leaves room for fast navigation while stopping scripted abuse.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));

        // AI calls are slow and billed per token: cap them per user.
        RateLimiter::for('ai', fn (Request $request) => [
            Limit::perMinute(5)->by('ai-min:'.$request->user()?->id),
            Limit::perDay(50)->by('ai-day:'.$request->user()?->id),
        ]);

        // An interview makes ~10 AI calls (start, one per answer, complete), so it gets its own budget.
        RateLimiter::for('ai-interview', fn (Request $request) => [
            Limit::perMinute(20)->by('ai-interview-min:'.$request->user()?->id),
            Limit::perDay(200)->by('ai-interview-day:'.$request->user()?->id),
        ]);
    }
}
