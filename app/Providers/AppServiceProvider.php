<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
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

        // Claude access (U-43): an access token lives an hour and is renewed with the refresh token, which lives 90
        // days; disconnecting a connection in the profile revokes both at once.
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(90));
        Passport::authorizationView('mcp.authorize');

        // 60 calls a minute for each connection of a user.
        RateLimiter::for('mcp', function (Request $request): Limit {
            $user = $request->user();
            $clientId = $user instanceof User ? $user->currentMcpClientId() : null;

            return Limit::perMinute(60)->by('mcp:'.($user?->getAuthIdentifier() ?? '').':'.($clientId ?? $request->ip()));
        });

        // Anyone may register a client (RFC 7591), so the registration and the token route are limited per address.
        RateLimiter::for('mcp-oauth', fn (Request $request): Limit => Limit::perMinute(10)->by('mcp-oauth:'.$request->ip()));
    }
}
