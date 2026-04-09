<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;
    public string $profileType;
    public string $profileTypeLabel;
    public string $accountType;
    public bool $isPremium;
    public int $expireMinutes;

    /**
     * Recipient address — required because notification MailChannel does not
     * call ->to() when sending a Mailable (only MailMessage gets recipients).
     */
    protected string $recipientEmail;

    public function __construct(
        object $notifiable,
        public string $resetUrl
    ) {
        $this->recipientEmail = $notifiable->email
            ?? (method_exists($notifiable, 'getEmailForPasswordReset') ? $notifiable->getEmailForPasswordReset() : null)
            ?? '';
        $this->userName = $notifiable->name ?? 'there';
        $this->profileType = $notifiable->profile_type ?? 'event';
        $this->profileTypeLabel = VerifyEmailMail::labelForProfileType($this->profileType);
        $this->accountType = $notifiable->account_type ?? 'free';
        $this->isPremium = $this->accountType === 'premium';
        $this->expireMinutes = (int) config('auth.passwords.users.expire', 60);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password - The Events Map',
            to: [
                new Address($this->recipientEmail, $this->userName),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reset-password',
        );
    }
}
