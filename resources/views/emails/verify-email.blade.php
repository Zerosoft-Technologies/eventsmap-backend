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
        $accountNote   = $isPremium
            ? 'with <strong style="color: #0061FF; font-weight: 600;">Premium</strong> access — giving you advanced features for managing and promoting your events.'
            : '. Upgrade to <strong style="color: #0061FF; font-weight: 600;">Premium</strong> anytime to unlock advanced features.';
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
    <p style="font-size: 16px; line-height: 1.65em; color: #3F3F46; margin-top: 0; margin-bottom: 12px;">Thank you for registering with <strong style="color: #111827; font-weight: 600;">The Events Map</strong>. We're excited to have you on board!</p>

    <p style="font-size: 15px; line-height: 1.65em; color: #52525B; margin-top: 0; margin-bottom: 26px;">To complete your registration and start exploring, please verify your email address by clicking the button below:</p>

    {{-- CTA Button --}}
    <x-mail::button :url="$verifyUrl">
        Verify Email Address
    </x-mail::button>

    {{-- Account Info Panel --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 28px 0; border-radius: 8px; border: 1px solid #E2E8F0;">
    <tr>
    <td style="padding: 0; border-radius: 8px; overflow: hidden;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
        <td width="4" style="background-color: #0061FF; width: 4px; border-radius: 8px 0 0 8px;">&nbsp;</td>
        <td style="padding: 14px 18px; background-color: #F8FAFC;">
            <p style="font-size: 10px; font-weight: 700; color: #6B7280; margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: 0.8px;">Your Account</p>
            <p style="font-size: 14px; color: #374151; margin: 0; line-height: 1.65;">Registered as a <strong style="color: #111827;">{{ $profileTypeLabel }}</strong> profile {!! $accountNote !!}</p>
        </td>
        </tr>
        </table>
    </td>
    </tr>
    </table>

    {{-- Disclaimer --}}
    <p style="font-size: 13px; line-height: 1.6em; color: #9CA3AF; margin-top: 0; margin-bottom: 22px;">If you did not create an account for The Events Map, please disregard this email — no action is required.</p>

    {{-- Divider --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 20px;">
    <tr><td style="height: 1px; background-color: #E4E4E7; font-size: 0; line-height: 0;">&nbsp;</td></tr>
    </table>

    {{-- Signature --}}
    <p style="font-size: 15px; color: #374151; margin: 0; line-height: 1.6;">Kind regards,<br><strong style="color: #111827; font-weight: 600;">The Events Map Team</strong></p>

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} The Events Map. All rights reserved.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
