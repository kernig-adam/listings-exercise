<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        // Inertia props are consumed directly by Vue, so the extra "data"
        // envelope around every resource just gets in the way. Paginated
        // collections keep their data/links/meta structure regardless.
        JsonResource::withoutWrapping();

        // Saving and deleting searches: generous for a person, tight for a script.
        RateLimiter::for('saved-searches', fn (Request $request) => Limit::perMinute(10)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Marking alerts read fires as cards are viewed, so it needs more headroom.
        RateLimiter::for('alerts', fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
