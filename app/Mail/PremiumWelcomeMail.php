<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PremiumWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;

    public string $profileTypeLabel;

    public string $companyName;

    public string $supportEmail;

    public string $dashboardUrl;

    protected string $recipientEmail;

    public function __construct(public User $user)
    {
        $this->recipientEmail = $user->email;
        $this->userName = VerifyEmailMail::displayNameForEmail($user->name ?: 'there');
        $this->profileTypeLabel = VerifyEmailMail::labelForProfileType($user->profile_type ?? 'event');
        $this->companyName = (string) config('invoice.company.name', config('app.name'));
        $this->supportEmail = (string) config('invoice.company.support_email', config('mail.from.address'));
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $this->dashboardUrl = $frontend !== '' ? $frontend.'/dashboard' : (string) config('app.url');
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from');

        return new Envelope(
            from: new Address($from['address'], $from['name']),
            to: [
                new Address($this->recipientEmail, $this->userName),
            ],
            subject: 'Welcome to '.$this->companyName.' Premium',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.premium-welcome',
            with: [
                'userName' => $this->userName,
                'profileTypeLabel' => $this->profileTypeLabel,
                'companyName' => $this->companyName,
                'supportEmail' => $this->supportEmail,
                'dashboardUrl' => $this->dashboardUrl,
            ],
        );
    }
}
