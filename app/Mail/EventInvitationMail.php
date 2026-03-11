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

    public string $acceptUrl;

    public string $rejectUrl;

    public function __construct(
        public EventInvitation $invitation
    ) {
        // $this->onQueue('emails');

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $token = $invitation->invitation_token;

        $this->acceptUrl = $frontendUrl . '/invitations/' . $invitation->id . '/respond?action=accept&token=' . $token;
        $this->rejectUrl = $frontendUrl . '/invitations/' . $invitation->id . '/respond?action=reject&token=' . $token;
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
