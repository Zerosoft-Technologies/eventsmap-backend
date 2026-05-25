<?php

namespace App\Http\Requests\Admin;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminInvoiceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
            'payment_status' => ['nullable', 'string', Rule::in([
                Invoice::PAYMENT_PAID,
                Invoice::PAYMENT_PENDING,
                Invoice::PAYMENT_FAILED,
            ])],
            'status' => ['nullable', 'string', Rule::in([
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_PAID,
                Invoice::STATUS_FAILED,
            ])],
            'currency' => 'nullable|string|size:3',
            'paid_from' => 'nullable|date',
            'paid_to' => 'nullable|date',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date',
            'sort' => 'nullable|string|in:created_at,paid_at,total_amount,invoice_number',
            'order' => 'nullable|string|in:asc,desc',
        ];
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }
}
