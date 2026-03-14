<?php

namespace App\Providers;

use App\Models\EventV2;
use App\Policies\EventV2Policy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // We use our own migration (2025_01_12_000005). Ignore Sanctum's so no duplicate table.
        Sanctum::ignoreMigrations();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(EventV2::class, EventV2Policy::class);
    }
}
