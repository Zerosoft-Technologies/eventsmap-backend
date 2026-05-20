<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.frontend_url', config('app.url'))">
            {{ $companyName }}
        </x-mail::header>
    </x-slot:header>

    @php
        $paidFormatted = strtoupper($invoice->currency).' '.number_format($invoice->total_amount / 100, 2, '.', ',');
        $paidDate = ($invoice->paid_at ?? $invoice->created_at)?->format('F j, Y');
    @endphp

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 0;">
    <tr>
    <td>
        <span style="display: inline-block; background-color: #0061FF; color: #FFFFFF; border: 1px solid #0049CC; font-size: 10px; font-weight: 700; padding: 3px 11px; border-radius: 99px; text-transform: uppercase; letter-spacing: 0.9px; margin-right: 6px;">Premium</span>
        <span style="display: inline-block; background-color: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; font-size: 10px; font-weight: 700; padding: 3px 11px; border-radius: 99px; text-transform: uppercase; letter-spacing: 0.9px;">Payment receipt</span>
    </td>
    </tr>
    </table>

    <h1 style="color: #111827; font-size: 22px; font-weight: 700; margin-top: 12px; margin-bottom: 6px; text-align: left; line-height: 1.3;">Hello, {{ $userName }}!</h1>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 18px 0;">
    <tr><td style="height: 1px; background-color: #E4E4E7; font-size: 0; line-height: 0;">&nbsp;</td></tr>
    </table>

    <p style="font-size: 16px; line-height: 1.65em; color: #3F3F46; margin-top: 0; margin-bottom: 12px;">Thank you for your purchase on <strong style="color: #111827; font-weight: 600;">{{ $companyName }}</strong>. Your payment was successful.</p>

    <p style="font-size: 15px; line-height: 1.65em; color: #52525B; margin-top: 0; margin-bottom: 26px;">Your invoice <strong style="color: #111827;">{{ $invoice->invoice_number }}</strong> is attached to this email as a PDF. You can also download it anytime from your account.</p>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 28px 0; border-radius: 8px; border: 1px solid #E2E8F0;">
    <tr>
    <td style="padding: 0; border-radius: 8px; overflow: hidden;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
        <td width="4" style="background-color: #0061FF; width: 4px; border-radius: 8px 0 0 8px;">&nbsp;</td>
        <td style="padding: 14px 18px; background-color: #F8FAFC;">
            <p style="font-size: 10px; font-weight: 700; color: #6B7280; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 0.8px;">Invoice summary</p>
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td style="font-size: 14px; color: #6B7280; padding: 4px 0;">Invoice number</td>
                <td style="font-size: 14px; color: #111827; font-weight: 600; padding: 4px 0; text-align: right;">{{ $invoice->invoice_number }}</td>
            </tr>
            <tr>
                <td style="font-size: 14px; color: #6B7280; padding: 4px 0;">Order ID</td>
                <td style="font-size: 14px; color: #374151; padding: 4px 0; text-align: right; word-break: break-all;">{{ $invoice->order_id }}</td>
            </tr>
            <tr>
                <td style="font-size: 14px; color: #6B7280; padding: 4px 0;">Date</td>
                <td style="font-size: 14px; color: #374151; padding: 4px 0; text-align: right;">{{ $paidDate }}</td>
            </tr>
            <tr>
                <td style="font-size: 14px; color: #6B7280; padding: 4px 0;">Description</td>
                <td style="font-size: 14px; color: #374151; padding: 4px 0; text-align: right;">{{ $invoice->product_description }}</td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 10px 0 4px;"><hr style="border: none; border-top: 1px solid #E2E8F0; margin: 0;"></td>
            </tr>
            <tr>
                <td style="font-size: 15px; color: #111827; font-weight: 700; padding: 4px 0;">Total paid</td>
                <td style="font-size: 15px; color: #0061FF; font-weight: 700; padding: 4px 0; text-align: right;">{{ $paidFormatted }}</td>
            </tr>
            </table>
        </td>
        </tr>
        </table>
    </td>
    </tr>
    </table>

    @if($downloadUrl)
    <x-mail::button :url="$downloadUrl">
        View invoices
    </x-mail::button>
    @endif

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 28px 0; border-radius: 8px; border: 1px solid #E2E8F0;">
    <tr>
    <td style="padding: 14px 18px; background-color: #FAFAFA; border-radius: 8px;">
        <p style="font-size: 13px; color: #6B7280; margin: 0; line-height: 1.65;">
            <strong style="color: #374151;">Attached:</strong> {{ $invoice->invoice_number }}.pdf<br>
            Questions about this invoice? Contact us at
            <a href="mailto:{{ $supportEmail }}" style="color: #0061FF; text-decoration: none;">{{ $supportEmail }}</a>.
        </p>
    </td>
    </tr>
    </table>

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
