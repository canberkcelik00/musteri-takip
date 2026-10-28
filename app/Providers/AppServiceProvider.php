<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire update requests re-run only the persistent middleware of the page's route.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);

        // Claude access (U-42): 60 calls a minute for each token.
        RateLimiter::for('mcp', function (Request $request): Limit {
            $user = $request->user();
            $token = $user instanceof User ? $user->currentAccessToken() : null;

            return Limit::perMinute(60)->by('mcp:'.($token?->getKey() ?? $request->ip()));
        });
    }
}
