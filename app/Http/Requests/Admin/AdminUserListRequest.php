<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminUserListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'account_type' => 'nullable|string|in:free,premium',
            'status' => 'nullable|string|in:active,pending_payment,suspended',
            'profile_type' => 'nullable|string|in:event,talent,organizer,venue',
            'country' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get per page value with default.
     */
    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }
}
