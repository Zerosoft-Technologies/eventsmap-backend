<?php

namespace App\Http\Requests\V2;

use App\Models\EventInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateGuestInvitationEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'receiver_type' => ['required', 'string', Rule::in(EventInvitation::TYPES)],
        ];
    }
}
