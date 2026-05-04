<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
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
            'profile_image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'avatar' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_profile_image' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->boolean('remove_profile_image')
                && ($this->hasFile('profile_image') || $this->hasFile('avatar'))) {
                $v->errors()->add(
                    'profile_image',
                    'Cannot upload a profile image and remove it in the same request.'
                );
            }
            if ($this->hasFile('profile_image') && $this->hasFile('avatar')) {
                $v->errors()->add('avatar', 'Send either profile_image or avatar, not both.');
            }
        });
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
