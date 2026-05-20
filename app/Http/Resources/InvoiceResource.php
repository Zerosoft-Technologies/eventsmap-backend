<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'currency' => strtoupper($this->currency),
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'product_description' => $this->product_description,
            'plan_interval' => $this->plan_interval,
            'quantity' => $this->quantity,
            'billing_name' => $this->billing_name,
            'billing_email' => $this->billing_email,
            'payment_method' => $this->payment_method,
            'payment_method_brand' => $this->payment_method_brand,
            'payment_method_last4' => $this->payment_method_last4,
            'pdf_url' => $this->pdfUrl(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
