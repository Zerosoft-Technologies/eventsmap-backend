<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>You've been invited to join EventsMap</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f1f5f9;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.08);">
                <tr>
                    <td style="background:linear-gradient(135deg,#0061FF 0%,#004bb5 100%);padding:28px 32px;">
                        <p style="margin:0;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.85);">EventsMap</p>
                        <h1 style="margin:12px 0 0;font-size:22px;line-height:1.3;font-weight:700;color:#ffffff;">You're invited</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#334155;">Hello{{ $invitation->invitee_name ? ' '.$invitation->invitee_name : '' }},</p>
                        <p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#334155;">
                            You have been invited to join an event on <strong style="color:#0061FF;">EventsMap</strong>.
                        </p>
                        <p style="margin:0 0 8px;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;">Your role</p>
                        <p style="margin:0 0 24px;font-size:18px;font-weight:700;color:#0f172a;">{{ $roleLabel }}</p>
                        @if($invitation->event)
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:24px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;">Event</p>
                                        <p style="margin:0;font-size:16px;font-weight:600;color:#0f172a;">{{ $invitation->event->title }}</p>
                                    </td>
                                </tr>
                            </table>
                        @endif
                        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#475569;">
                            An event publisher has invited you to participate. To get started, create your profile using the button below.
                        </p>
                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
                            <tr>
                                <td align="center" style="border-radius:10px;background:#FF7700;">
                                    <a href="{{ $registrationUrl }}" target="_blank" rel="noopener"
                                       style="display:inline-block;padding:14px 32px;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">
                                        Create My Profile
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:#64748b;">
                            This invitation will expire in {{ \App\Models\EventInvitation::GUEST_TOKEN_EXPIRY_DAYS }} days.
                        </p>
                        <p style="margin:0;font-size:13px;line-height:1.5;color:#94a3b8;">
                            If you did not expect this invitation, you may safely ignore this email.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                        <p style="margin:0;font-size:13px;color:#64748b;">Thank you,<br><strong style="color:#334155;">EventsMap Team</strong></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
