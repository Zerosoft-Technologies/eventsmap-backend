<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            line-height: 1.45;
            padding: 36px 42px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 28px;
        }
        .header-left, .header-right {
            display: table-cell;
            vertical-align: top;
        }
        .header-right { text-align: right; width: 45%; }
        .logo { max-height: 48px; max-width: 180px; margin-bottom: 8px; }
        .doc-title {
            font-size: 22px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .doc-subtitle { font-size: 10px; color: #64748b; margin-top: 4px; }
        .badge {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .meta-grid {
            display: table;
            width: 100%;
            margin-bottom: 24px;
        }
        .meta-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 16px;
        }
        .section-title {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .section-body { font-size: 11px; color: #334155; }
        .section-body strong { color: #0f172a; }
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 20px 0;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 20px;
        }
        table.items th {
            background: #f8fafc;
            text-align: left;
            padding: 10px 12px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        table.items td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        table.items td.amount { text-align: right; white-space: nowrap; }
        .summary {
            width: 280px;
            margin-left: auto;
        }
        .summary-row {
            display: table;
            width: 100%;
            padding: 6px 0;
        }
        .summary-row span {
            display: table-cell;
        }
        .summary-row span:last-child { text-align: right; }
        .summary-total {
            border-top: 2px solid #0f172a;
            margin-top: 8px;
            padding-top: 10px;
            font-size: 14px;
            font-weight: bold;
        }
        .footer {
            margin-top: 36px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
        .footer p { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="{{ $company['name'] }}" class="logo" style="width: 80px; height: auto;">
            @else
                <div style="font-size: 18px; font-weight: bold; color: #0f172a;">{{ $company['name'] }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">Invoice / Receipt</div>
            <div class="doc-subtitle">Thank you for your purchase</div>
            @php
                $statusClass = match($invoice->payment_status) {
                    'paid' => 'badge-paid',
                    'failed' => 'badge-failed',
                    default => 'badge-pending',
                };
            @endphp
            <span class="badge {{ $statusClass }}">{{ ucfirst($invoice->payment_status) }}</span>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-col">
            <div class="section-title">Invoice details</div>
            <div class="section-body">
                <strong>Invoice number:</strong> {{ $invoice->invoice_number }}<br>
                <strong>Order ID:</strong> {{ $invoice->order_id }}<br>
                <strong>Date:</strong> {{ ($invoice->paid_at ?? $invoice->created_at)?->format('F j, Y') }}<br>
                @if($invoice->stripe_payment_intent)
                    <strong>Transaction ID:</strong> {{ $invoice->stripe_payment_intent }}<br>
                @endif
                <strong>Currency:</strong> {{ $currencyUpper }}
            </div>
        </div>
        <div class="meta-col">
            <div class="section-title">Payment</div>
            <div class="section-body">
                <strong>Method:</strong> {{ $paymentMethodLabel }}<br>
                <strong>Status:</strong> {{ ucfirst($invoice->payment_status) }}
            </div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-col">
            <div class="section-title">From</div>
            <div class="section-body">
                <strong>{{ $company['name'] }}</strong><br>
                @if(!empty($company['address']))
                    {!! nl2br(e($company['address'])) !!}<br>
                @endif
                @if(!empty($company['vat_number']))
                    VAT: {{ $company['vat_number'] }}<br>
                @endif
                {{ $company['support_email'] }}
            </div>
        </div>
        <div class="meta-col">
            <div class="section-title">Bill to</div>
            <div class="section-body">
                <strong>{{ $invoice->billing_name }}</strong><br>
                {{ $invoice->billing_email }}<br>
                @if($invoice->billing_address)
                    {!! nl2br(e($invoice->billing_address)) !!}
                @endif
            </div>
        </div>
    </div>

    <hr class="divider">

    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%;">Qty</th>
                <th style="width: 62%;">Description</th>
                <th style="width: 30%;" class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->quantity }}</td>
                <td>
                    <strong>{{ $invoice->product_description }}</strong>
                    @if($periodLabel)
                        <br><span style="color: #64748b; font-size: 10px;">Billing period: {{ $periodLabel }}</span>
                    @endif
                </td>
                <td class="amount">{{ $subtotalFormatted }}</td>
            </tr>
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-row">
            <span>Subtotal</span>
            <span>{{ $subtotalFormatted }}</span>
        </div>
        <div class="summary-row">
            <span>Tax / VAT</span>
            <span>{{ $taxFormatted }}</span>
        </div>
        <div class="summary-row summary-total">
            <span>Total</span>
            <span>{{ $totalFormatted }}</span>
        </div>
    </div>

    <div class="footer">
        <p><strong>Thank you for your business.</strong></p>
        <p>Questions about this invoice? Contact {{ $company['support_email'] }}</p>
        <p>This document was generated electronically and is valid without a signature.</p>
    </div>
</body>
</html>
