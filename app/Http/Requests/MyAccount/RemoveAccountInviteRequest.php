<?php

namespace App\Http\Requests\MyAccount;

use Illuminate\Foundation\Http\FormRequest;

class RemoveAccountInviteRequest extends FormRequest
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
            'event_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:talent,organiser,venue,organizer'],
            'profile_id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');
        if (is_string($type) && $type === 'organizer') {
            $this->merge(['type' => 'organiser']);
        }
    }
}
