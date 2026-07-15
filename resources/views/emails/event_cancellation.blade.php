<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Cancelled - {{ $event->title }}</title>
    <style>
        body { font-family: Inter, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .card { background: #fff; border-radius: 8px; padding: 32px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        h1 { font-size: 24px; margin: 0 0 24px 0; color: #1a1a1a; }
        .info-row { margin-bottom: 12px; padding: 8px 0; border-bottom: 1px solid #eee; }
        .label { font-weight: 600; color: #666; font-size: 12px; text-transform: uppercase; }
        .value { font-size: 16px; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Event Cancelled</h1>

        <p>Hello {{ $invitation->receiver->name ?? 'there' }},</p>

        <p>The event <strong>{{ $event->title }}</strong> has been cancelled by {{ $actor->name ?? 'the event publisher' }}.</p>

        <div class="info-row">
            <div class="label">Your role</div>
            <div class="value">{{ ucfirst($invitation->receiver_type) }}</div>
        </div>

        @if($event->event_date)
        <div class="info-row">
            <div class="label">Scheduled date</div>
            <div class="value">{{ $event->event_date->format('l, F j, Y') }}</div>
        </div>
        @endif

        <p>Please check your notifications on The Events Map for details.</p>

        <p>— The Events Map Team</p>
    </div>
</body>
</html>
