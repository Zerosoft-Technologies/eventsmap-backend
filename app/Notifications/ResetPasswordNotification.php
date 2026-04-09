<?php

namespace App\Notifications;

use App\Mail\ResetPasswordMail;
use Illuminate\Notifications\Notification;

/**
 * Custom ResetPasswordNotification for API/SPA applications.
 *
 * Generates a frontend URL instead of using Laravel's route() helper.
 * Note: This notification sends immediately (not queued) to ensure
 * password reset emails are delivered without requiring a queue worker.
 */
class ResetPasswordNotification extends Notification
{

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): ResetPasswordMail
    {
        return new ResetPasswordMail($notifiable, $this->resetUrl($notifiable));
    }

    /**
     * Generate the reset URL for the frontend application.
     */
    protected function resetUrl(object $notifiable): string
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));

        return $frontendUrl . '/auth/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
