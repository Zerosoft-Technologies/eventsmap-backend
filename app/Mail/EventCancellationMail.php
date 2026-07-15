<?php

namespace App\Mail;

use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public EventV2 $event,
        public EventInvitation $invitation,
        public User $actor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Event Cancelled: '.$this->event->title,
            from: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event_cancellation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
