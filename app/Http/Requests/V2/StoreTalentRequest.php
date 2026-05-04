<?php

namespace App\Http\Requests\V2;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTalentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * JSON clients often send age as a number; validation expects a string.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('age') && is_numeric($this->input('age'))) {
            $this->merge(['age' => (string) $this->input('age')]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|min:3|max:255',
            'event_type' => ['required', 'string', Rule::in(['free', 'premium'])],
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'integer|exists:subcategories,id',
            'talent_category_id' => 'nullable|integer|exists:talent_categories,id',
            'talent_subcategory_ids' => 'nullable|array',
            'talent_subcategory_ids.*' => 'integer|exists:talent_subcategories,id',
            'city' => 'nullable|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'description' => 'nullable|string|max:10000',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email',
            'contact_website' => 'nullable|url|max:500',
            'contact_box_message' => 'nullable|string|max:1000',
            'contact_box_design_message' => 'nullable|string|max:10000',
            'facebook_url' => 'nullable|url|max:500',
            'instagram_url' => 'nullable|url|max:500',
            'tiktok_url' => 'nullable|url|max:500',
            'fan_club_url' => 'nullable|url|max:500',
            'nationality' => 'nullable|string|max:255',
            'show_nationality' => 'nullable|string|max:32',
            'age' => 'nullable|string|max:10',
            'show_age' => 'nullable|string|max:32',
            'languages' => 'nullable|array',
            'languages.*' => 'nullable|string|max:100',
            'highlights' => 'nullable|string|max:10000',
            'show_upcoming_events' => 'nullable|boolean',
            'show_past_events' => 'nullable|boolean',
            'status' => 'prohibited',
            'publish_status' => 'prohibited',
        ];

        if ($this->hasFile('image_path')) {
            $rules['image_path'] = 'required|file|image|mimes:jpeg,jpg,png,webp|max:2048';
        } else {
            $rules['image_path'] = 'required|string';
        }

        if ($this->hasFile('additional_images')) {
            $rules['additional_images'] = 'nullable|array';
            $rules['additional_images.*'] = 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:2048';
        } else {
            $rules['additional_images'] = 'nullable|array';
            $rules['additional_images.*'] = 'nullable|string';
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('image_path') && !$this->hasFile('image_path')) {
                $user = $this->user();
                $imageId = $this->input('image_path');

                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imageId)) {
                    $validator->errors()->add('image_path', 'Invalid image ID format');
                } else {
                    $galleryImage = \App\Models\GalleryImage::where('image_id', $imageId)
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false)
                        ->first();

                    if (!$galleryImage) {
                        $validator->errors()->add('image_path', 'Image not found or does not belong to you');
                    }
                }
            }

            if ($this->filled('additional_images') && is_array($this->input('additional_images')) && !$this->hasFile('additional_images')) {
                $user = $this->user();
                foreach ($this->input('additional_images') as $index => $imageId) {
                    if (is_null($imageId) || $imageId === '') {
                        continue;
                    }

                    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imageId)) {
                        $validator->errors()->add("additional_images.{$index}", 'Invalid image ID format');
                        continue;
                    }

                    $galleryImage = \App\Models\GalleryImage::where('image_id', $imageId)
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false)
                        ->first();

                    if (!$galleryImage) {
                        $validator->errors()->add("additional_images.{$index}", 'Image not found or does not belong to you');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Talent title is required',
            'title.min' => 'Talent title must be at least 3 characters',
            'event_type.required' => 'Event type is required',
            'event_type.in' => 'Event type must be free or premium',
            'address.required' => 'Address is required',
            'image_path.required' => 'Main image is required',
            'image_path.image' => 'File must be an image',
            'image_path.max' => 'Image must not exceed 2MB',
            'image_path.mimes' => 'Image must be jpg, jpeg, png, or webp format',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
