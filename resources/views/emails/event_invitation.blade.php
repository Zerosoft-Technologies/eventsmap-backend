<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Invitation - {{ $invitation->event->title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
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
        .buttons {
            margin-top: 32px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            transition: opacity 0.2s;
        }
        .btn-accept {
            background-color: #16a34a;
            color: #ffffff !important;
        }
        .btn-reject {
            background-color: #dc2626;
            color: #ffffff !important;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
        .expiry-note {
            margin-top: 20px;
            font-size: 13px;
            color: #888;
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

        <div class="buttons">
            <a href="{{ $acceptUrl }}" class="btn btn-accept">Accept Invitation</a>
            <!-- <a href="{{ $rejectUrl }}" class="btn btn-reject">Decline Invitation</a> -->
        </div>

        <p class="expiry-note">This invitation link expires in 48 hours.</p>

        <div class="footer">
            <p>If you have any questions, please contact the event organizer.</p>
            <p>— The Events Map Team</p>
        </div>
    </div>
</body>
</html>
