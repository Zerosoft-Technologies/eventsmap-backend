<?php

namespace App\Providers;

use App\Models\EventV2;
use App\Policies\EventV2Policy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(EventV2::class, EventV2Policy::class);
    }
}
