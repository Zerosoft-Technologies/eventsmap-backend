<?php

namespace App\Providers;

use App\Models\EventV2;
use App\Policies\EventV2Policy;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
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

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verify Email Address')
                ->greeting('Hello,')
                ->line('Thank you for registering with The Events Map.')
                ->line('To complete your registration, please verify your email address by clicking the button below:')
                ->action('Verify Email Address', $url)
                ->line('Depending on your registration, this account may be set up as a Premium Organiser account, giving you access to advanced features for managing and promoting your events.')
                ->line('If you did not create an account for The Events Map, no further action is required.')
                ->salutation('Kind regards,' . PHP_EOL . 'The Events Map Team');
        });
    }
}
