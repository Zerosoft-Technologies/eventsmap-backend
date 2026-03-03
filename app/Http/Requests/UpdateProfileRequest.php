<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => 'sometimes|required|string|max:255',
            'password' => [
                'sometimes',
                'required',
                'string',
                Password::min(8),
            ],
            'billing_type' => 'sometimes|nullable|in:private,business',
            'company_name' => 'sometimes|nullable|string|max:255',
            'vat_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                // Don't allow updating VAT if it's already validated
                function ($attribute, $value, $fail) use ($user) {
                    if ($user && $user->vat_validated && $value !== $user->vat_number) {
                        $fail('The VAT number cannot be updated once it has been validated.');
                    }
                }
            ],
            'address' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vat_number' => 'The VAT number cannot be updated once it has been validated.',
        ];
    }
}
