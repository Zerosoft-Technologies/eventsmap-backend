<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
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
        $shared = [
            'alt_text' => 'nullable|string|max:500',
            'event_id' => [
                'nullable',
                'integer',
                'exists:events,id',
            ],
        ];

        if ($this->uploadPhpError() !== null) {
            return $shared;
        }

        return array_merge($shared, [
            'image' => [
                'required',
                'file',
                'max:10240', // 10MB in kilobytes
                'mimes:jpeg,png,gif,webp',
            ],
        ]);
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
            'image.uploaded'  => 'The image failed to upload. The file may exceed the server upload limit (max 10MB).',
            'image.max'       => 'File size must not exceed 10MB.',
            'image.mimes'     => 'Only JPEG, PNG, GIF, and WebP images are allowed.',
            'alt_text.max'    => 'Alt text must not exceed 500 characters.',
            'event_id.exists' => 'Event not found.',
        ];
    }

    /**
     * Surface PHP upload errors (e.g. upload_max_filesize) with actionable messages.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $code = $this->uploadPhpError();
            if ($code === null) {
                return;
            }

            $message = match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    'File size exceeds the server upload limit. Maximum allowed size is 10MB. '
                    . 'Increase PHP upload_max_filesize and post_max_size (e.g. 12M / 14M) and restart the web server.',
                UPLOAD_ERR_PARTIAL => 'The image was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_FILE => 'An image file is required.',
                default => 'The image failed to upload. Please try a smaller file or a different format.',
            };

            $validator->errors()->add('image', $message);
        });
    }

    /**
     * @return int|null PHP $_FILES error code, or null when upload is OK / not present.
     */
    private function uploadPhpError(): ?int
    {
        if ($this->hasFile('image')) {
            return null;
        }

        $upload = $_FILES['image'] ?? null;
        if (! is_array($upload) || ! isset($upload['error'])) {
            return null;
        }

        $error = (int) $upload['error'];

        return $error === UPLOAD_ERR_OK ? null : $error;
    }
}
