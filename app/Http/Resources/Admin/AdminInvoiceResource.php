<?php

namespace App\Http\Resources\Admin;

use App\Models\Invoice;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class AdminInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = strtoupper((string) $this->currency);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'account_type' => $this->user->account_type,
                ];
            }),
            'subscription_id' => $this->subscription_id,
            'invoice_number' => $this->invoice_number,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'amount_formatted' => MoneyFormatter::format((int) $this->amount, $currency),
            'tax_amount_formatted' => MoneyFormatter::format((int) $this->tax_amount, $currency),
            'total_amount_formatted' => MoneyFormatter::format((int) $this->total_amount, $currency),
            'currency' => $currency,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'product_description' => $this->product_description,
            'plan_interval' => $this->plan_interval,
            'quantity' => $this->quantity,
            'billing_name' => $this->billing_name,
            'billing_email' => $this->billing_email,
            'billing_address' => $this->billing_address,
            'payment_method' => $this->payment_method,
            'payment_method_brand' => $this->payment_method_brand,
            'payment_method_last4' => $this->payment_method_last4,
            'stripe_payment_intent' => $this->stripe_payment_intent,
            'stripe_session_id' => $this->stripe_session_id,
            'stripe_invoice_id' => $this->stripe_invoice_id,
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'pdf_url' => $this->pdfUrl(),
            'has_pdf' => (bool) $this->invoice_pdf_path,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'emailed_at' => $this->emailed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
