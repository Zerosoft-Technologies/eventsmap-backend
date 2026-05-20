<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.frontend_url', config('app.url'))">
            {{ $companyName }}
        </x-mail::header>
    </x-slot:header>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 0;">
    <tr>
    <td>
        <span style="display: inline-block; background-color: #0061FF; color: #FFFFFF; border: 1px solid #0049CC; font-size: 10px; font-weight: 700; padding: 3px 11px; border-radius: 99px; text-transform: uppercase; letter-spacing: 0.9px; margin-right: 6px;">Premium</span>
        <span style="display: inline-block; background-color: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; font-size: 10px; font-weight: 700; padding: 3px 11px; border-radius: 99px; text-transform: uppercase; letter-spacing: 0.9px;">{{ $profileTypeLabel }}</span>
    </td>
    </tr>
    </table>

    <h1 style="color: #111827; font-size: 22px; font-weight: 700; margin-top: 12px; margin-bottom: 6px; text-align: left; line-height: 1.3;">Welcome, {{ $userName }}!</h1>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 18px 0;">
    <tr><td style="height: 1px; background-color: #E4E4E7; font-size: 0; line-height: 0;">&nbsp;</td></tr>
    </table>

    <p style="font-size: 16px; line-height: 1.65em; color: #3F3F46; margin-top: 0; margin-bottom: 12px;">Your payment was successful and your <strong style="color: #0061FF; font-weight: 600;">Premium</strong> account on <strong style="color: #111827; font-weight: 600;">{{ $companyName }}</strong> is now active.</p>

    <p style="font-size: 15px; line-height: 1.65em; color: #52525B; margin-top: 0; margin-bottom: 26px;">You now have access to premium features for your {{ $profileTypeLabel }} profile, including advanced tools to manage and promote your events.</p>

    <x-mail::button :url="$dashboardUrl">
        Go to your dashboard
    </x-mail::button>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 28px 0; border-radius: 8px; border: 1px solid #E2E8F0;">
    <tr>
    <td style="padding: 0; border-radius: 8px; overflow: hidden;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
        <td width="4" style="background-color: #0061FF; width: 4px; border-radius: 8px 0 0 8px;">&nbsp;</td>
        <td style="padding: 14px 18px; background-color: #F8FAFC;">
            <p style="font-size: 10px; font-weight: 700; color: #6B7280; margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: 0.8px;">What happens next</p>
            <p style="font-size: 14px; color: #374151; margin: 0; line-height: 1.65;">
                Your invoice/receipt is sent in a separate email with the PDF attached.<br>
                You can download past invoices anytime from your account settings.
            </p>
        </td>
        </tr>
        </table>
    </td>
    </tr>
    </table>

    <p style="font-size: 13px; line-height: 1.6em; color: #9CA3AF; margin-top: 0; margin-bottom: 22px;">Need help getting started? Contact <a href="mailto:{{ $supportEmail }}" style="color: #0061FF; text-decoration: none;">{{ $supportEmail }}</a>.</p>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 20px;">
    <tr><td style="height: 1px; background-color: #E4E4E7; font-size: 0; line-height: 0;">&nbsp;</td></tr>
    </table>

    <p style="font-size: 15px; color: #374151; margin: 0; line-height: 1.6;">Kind regards,<br><strong style="color: #111827; font-weight: 600;">{{ $companyName }} Team</strong></p>

    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ $companyName }}. All rights reserved.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
