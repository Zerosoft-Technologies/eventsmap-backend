<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    @php
        $profileColors = [
            'event'     => ['bg' => '#F0FDF4', 'color' => '#166534', 'border' => '#BBF7D0'],
            'organiser' => ['bg' => '#FFF7ED', 'color' => '#9A3412', 'border' => '#FED7AA'],
            'organizer' => ['bg' => '#FFF7ED', 'color' => '#9A3412', 'border' => '#FED7AA'],
            'talent'    => ['bg' => '#FDF4FF', 'color' => '#7E22CE', 'border' => '#E9D5FF'],
            'venue'     => ['bg' => '#ECFEFF', 'color' => '#155E75', 'border' => '#A5F3FC'],
        ];
        $pc = $profileColors[strtolower((string) $profileType)] ?? $profileColors['event'];
        $accountBg     = $isPremium ? '#0061FF' : '#F1F5F9';
        $accountColor  = $isPremium ? '#FFFFFF'  : '#475569';
        $accountBorder = $isPremium ? '#0049CC'  : '#CBD5E1';
        $accountLabel  = $isPremium ? 'Premium'  : 'Free';
    @endphp

    {{-- Account & Profile Type Badges --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 0;">
    <tr>
    <td>
        <span style="display: inline-block; background-color: {{ $accountBg }}; color: {{ $accountColor }}; border: 1px solid {{ $accountBorder }}; font-size: 10px; font-weight: 700; padding: 3px 11px; border-radius: 99px; text-transform: uppercase; letter-spacing: 0.9px; margin-right: 6px;">{{ $accountLabel }}</span><span style="display: inline-block; background-color: {{ $pc['bg'] }}; color: {{ $pc['color'] }}; border: 1px solid {{ $pc['border'] }}; font-size: 10px; font-weight: 700; padding: 3px 11px; border-radius: 99px; text-transform: uppercase; letter-spacing: 0.9px;">{{ $profileTypeLabel }}</span>
    </td>
    </tr>
    </table>

    {{-- Greeting --}}
    <h1 style="color: #111827; font-size: 22px; font-weight: 700; margin-top: 12px; margin-bottom: 6px; text-align: left; line-height: 1.3;">Hello, {{ $userName }}!</h1>

    {{-- Divider --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 18px 0;">
    <tr><td style="height: 1px; background-color: #E4E4E7; font-size: 0; line-height: 0;">&nbsp;</td></tr>
    </table>

    {{-- Body text --}}
    <p style="font-size: 16px; line-height: 1.65em; color: #3F3F46; margin-top: 0; margin-bottom: 12px;">We received a <strong style="color: #111827; font-weight: 600;">password reset request</strong> for your account on The Events Map.</p>

    <p style="font-size: 15px; line-height: 1.65em; color: #52525B; margin-top: 0; margin-bottom: 26px;">Click the button below to set a new password. This link will expire in <strong style="color: #111827;">{{ $expireMinutes }} minutes</strong>.</p>

    {{-- CTA Button --}}
    <x-mail::button :url="$resetUrl">
        Reset Password
    </x-mail::button>

    {{-- Security Warning Panel --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 28px 0; border-radius: 8px; border: 1px solid #FDE68A;">
    <tr>
    <td style="padding: 0; border-radius: 8px; overflow: hidden;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
        <td width="4" style="background-color: #F59E0B; width: 4px; border-radius: 8px 0 0 8px;">&nbsp;</td>
        <td style="padding: 14px 18px; background-color: #FFFBEB;">
            <p style="font-size: 10px; font-weight: 700; color: #92400E; margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: 0.8px;">Security Notice</p>
            <p style="font-size: 14px; color: #78350F; margin: 0; line-height: 1.65;">If you did not request a password reset, please ignore this email. Your account remains secure and no changes have been made.</p>
        </td>
        </tr>
        </table>
    </td>
    </tr>
    </table>

    {{-- Divider --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 20px;">
    <tr><td style="height: 1px; background-color: #E4E4E7; font-size: 0; line-height: 0;">&nbsp;</td></tr>
    </table>

    {{-- Signature --}}
    <p style="font-size: 15px; color: #374151; margin: 0; line-height: 1.6;">Best regards,<br><strong style="color: #111827; font-weight: 600;">The Events Map Team</strong></p>

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} The Events Map. All rights reserved.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
