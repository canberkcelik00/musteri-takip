<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Support\Settings;
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
    }
}
