<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;
    public string $profileType;
    public string $profileTypeLabel;
    public string $accountType;
    public bool $isPremium;

    /**
     * Recipient address — required because notification MailChannel does not
     * call ->to() when sending a Mailable (only MailMessage gets recipients).
     */
    protected string $recipientEmail;

    public function __construct(
        object $notifiable,
        public string $verifyUrl
    ) {
        $this->recipientEmail = $notifiable->email
            ?? (method_exists($notifiable, 'getEmailForVerification') ? $notifiable->getEmailForVerification() : null)
            ?? '';
        $rawName = $notifiable->name ?? null;
        $this->userName = ($rawName === null || $rawName === '')
            ? 'there'
            : self::displayNameForEmail((string) $rawName);
        $this->profileType = $notifiable->profile_type ?? 'event';
        $this->profileTypeLabel = self::labelForProfileType($this->profileType);
        $this->accountType = $notifiable->account_type ?? 'free';
        $this->isPremium = $this->accountType === 'premium';
    }

    /**
     * Capitalize the first character only when it is lowercase; if the user
     * already used an uppercase first letter, leave the name unchanged.
     */
    public static function displayNameForEmail(string $name): string
    {
        $first = mb_substr($name, 0, 1, 'UTF-8');
        $lower = mb_strtolower($first, 'UTF-8');
        $upper = mb_strtoupper($first, 'UTF-8');

        if ($first === $lower && $lower !== $upper) {
            return $upper.mb_substr($name, 1, null, 'UTF-8');
        }

        return $name;
    }

    public static function labelForProfileType(string $type): string
    {
        return match (strtolower($type)) {
            'event' => 'Event',
            'organizer', 'organiser' => 'Event organizer',
            'talent' => 'Talent',
            'venue' => 'Venue',
            default => ucfirst($type),
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Email Address - The Events Map',
            to: [
                new Address($this->recipientEmail, $this->userName),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.verify-email',
        );
    }
}
