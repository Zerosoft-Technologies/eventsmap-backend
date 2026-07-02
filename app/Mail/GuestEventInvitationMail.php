<?php

namespace App\Mail;

use App\Models\EventInvitation;
use App\Services\V2\GuestInvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestEventInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $registrationUrl;

    public string $roleLabel;

    public function __construct(
        public EventInvitation $invitation,
    ) {
        $this->registrationUrl = app(GuestInvitationService::class)->registrationUrl($invitation);
        $this->roleLabel = match ($invitation->receiver_type) {
            EventInvitation::TYPE_ORGANISER => 'Organiser',
            EventInvitation::TYPE_TALENT => 'Talent',
            EventInvitation::TYPE_VENUE => 'Venue',
            default => ucfirst((string) $invitation->receiver_type),
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join The Events Map",
            from: config('mail.from.address'),
            replyTo: [$this->invitation->sender->email ?? config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest_event_invitation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
