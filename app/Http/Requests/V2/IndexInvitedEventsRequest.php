<?php

namespace App\Http\Requests\V2;

use Illuminate\Foundation\Http\FormRequest;

class IndexInvitedEventsRequest extends FormRequest
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
            'status' => 'nullable|string|in:pending,accepted,rejected',
            'event_timing' => 'nullable|string|in:upcoming,past',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
