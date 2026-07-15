<?php

namespace App\Providers;

use App\Mail\VerifyEmailMail;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Policies\EventV2Policy;
use App\Policies\RecurringSeriesPolicy;
use App\Services\V2\RecurringSeriesLifecycleService;
use Illuminate\Auth\Notifications\VerifyEmail;
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
        Gate::policy(RecurringSeries::class, RecurringSeriesPolicy::class);

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return new VerifyEmailMail($notifiable, $url);
        });

        User::deleting(function (User $user): void {
            app(RecurringSeriesLifecycleService::class)->handlePremiumExpiry($user, $user);
        });
    }
}
