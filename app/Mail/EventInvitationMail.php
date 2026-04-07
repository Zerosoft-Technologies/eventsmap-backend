<?php

namespace App\Mail;

use App\Models\EventInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventInvitationMail extends Mailable
{
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public EventInvitation $invitation
    ) {
        // $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        $event = $this->invitation->event;
        $sender = $this->invitation->sender;

        return new Envelope(
            subject: 'Event Invitation: ' . $event->title,
            from: config('mail.from.address'),
            replyTo: [$sender->email ?? config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event_invitation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
