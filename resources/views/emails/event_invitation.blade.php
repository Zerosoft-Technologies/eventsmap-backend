<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Invitation - {{ $invitation->event->title }}</title>
    <style>
        body {
            font-family: Inter, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .card {
            background: #ffffff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        h1 {
            font-size: 24px;
            margin: 0 0 24px 0;
            color: #1a1a1a;
        }
        .info-row {
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-of-type {
            border-bottom: none;
        }
        .label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .value {
            font-size: 16px;
            margin-top: 4px;
        }
        .login-notice {
            margin-top: 32px;
            padding: 16px 20px;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            font-size: 15px;
            color: #1e40af;
            line-height: 1.5;
        }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>You're Invited!</h1>

        <p>Hello {{ $invitation->receiver->name }},</p>

        <p>You have been invited to participate in an event as an invited <strong>{{ ucfirst($invitation->receiver_type) }}</strong>.</p>

        <div class="info-row">
            <div class="label">Event Name</div>
            <div class="value">{{ $invitation->event->title }}</div>
        </div>

        <div class="info-row">
            <div class="label">Invited By</div>
            <div class="value">{{ $invitation->sender->name }}</div>
        </div>

        <div class="info-row">
            <div class="label">Event Date</div>
            <div class="value">{{ $invitation->event->event_date->format('l, F j, Y') }}</div>
        </div>

        <div class="info-row">
            <div class="label">Time</div>
            <div class="value">{{ $invitation->event->start_time }} – {{ $invitation->event->end_time }}</div>
        </div>

        <div class="info-row">
            <div class="label">Location</div>
            <div class="value">{{ $invitation->event->address }}</div>
        </div>

        <p>By accepting, you will be able to participate in the event chat and coordinate with other participants.</p>

        <div class="login-notice">
            To respond to this invitation, please <strong>log in to your account</strong> on The Events Map website. You will find the Accept and Decline options in your notifications.
        </div>

        <div class="footer">
            <p>If you have any questions, please contact the event organizer.</p>
            <p>— The Events Map Team</p>
        </div>
    </div>
</body>
</html>
