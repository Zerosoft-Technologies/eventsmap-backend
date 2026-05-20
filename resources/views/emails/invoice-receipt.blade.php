<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #334155; max-width: 600px; margin: 0 auto; padding: 24px;">
    <h1 style="color: #0f172a; font-size: 20px;">Payment receipt</h1>
    <p>Hi {{ $invoice->billing_name }},</p>
    <p>Thank you for your purchase. Your payment was successful and your invoice is attached to this email.</p>
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 8px 0; color: #64748b;">Invoice number</td>
            <td style="padding: 8px 0; text-align: right;"><strong>{{ $invoice->invoice_number }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #64748b;">Order ID</td>
            <td style="padding: 8px 0; text-align: right;">{{ $invoice->order_id }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #64748b;">Amount paid</td>
            <td style="padding: 8px 0; text-align: right;"><strong>{{ strtoupper($invoice->currency) }} {{ number_format($invoice->total_amount / 100, 2) }}</strong></td>
        </tr>
    </table>
    <p style="font-size: 14px;">You can also download this invoice anytime from your account.</p>
    <p style="font-size: 13px; color: #94a3b8;">Questions? Contact <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
    <p style="font-size: 13px; color: #94a3b8;">— {{ $companyName }}</p>
</body>
</html>
