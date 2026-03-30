<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadGalleryImageRequest extends FormRequest
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
        return [
            'image' => [
                'required',
                'file',
                'max:5120', // 5MB in kilobytes
                'mimes:jpeg,png,gif,webp',
            ],
            'alt_text' => 'nullable|string|max:500',
            'event_id' => [
                'nullable',
                'integer',
                'exists:events,id',
            ],
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
            'image.required'  => 'An image file is required.',
            'image.file'      => 'The uploaded item must be a valid file.',
            'image.max'       => 'File size must not exceed 5MB.',
            'image.mimes'     => 'Only JPEG, PNG, GIF, and WebP images are allowed.',
            'alt_text.max'    => 'Alt text must not exceed 500 characters.',
            'event_id.exists' => 'Event not found.',
        ];
    }
}
