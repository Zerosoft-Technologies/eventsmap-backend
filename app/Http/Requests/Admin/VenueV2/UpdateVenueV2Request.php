<?php

namespace App\Http\Requests\Admin\VenueV2;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateVenueV2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('show_upcoming_events')) {
            $this->merge(['show_upcoming_events' => filter_var($this->show_upcoming_events, FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('show_past_events')) {
            $this->merge(['show_past_events' => filter_var($this->show_past_events, FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('is_approved')) {
            $this->merge(['is_approved' => filter_var($this->is_approved, FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'user_id' => 'sometimes|integer|exists:users,id',
            'title' => 'sometimes|string|min:3|max:255',
            'event_type' => 'nullable|string|max:50',
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'subcategory_ids' => 'sometimes|nullable|array',
            'subcategory_ids.*' => 'integer|exists:subcategories,id',
            'venue_category_id' => 'sometimes|nullable|integer|exists:venue_categories,id',
            'venue_subcategory_ids' => 'sometimes|nullable|array',
            'venue_subcategory_ids.*' => 'integer|exists:venue_subcategories,id',
            'address' => 'sometimes|string|max:500',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'description' => 'sometimes|nullable|string|max:10000',
            'description_items' => 'sometimes|nullable|array',
            'description_items.*' => 'string|max:500',
            'allow_dogs' => 'sometimes|nullable|boolean',
            'allowance_of_dogs' => 'sometimes|nullable|string|max:500',
            'wheelchair_accessible' => 'sometimes|nullable|boolean',
            'accessibility_description' => 'sometimes|nullable|string|max:1000',
            'parking' => 'sometimes|nullable|boolean',
            'valet' => 'sometimes|nullable|boolean',
            'play_area' => 'sometimes|nullable|boolean',
            'contact_phone' => 'sometimes|nullable|string|max:50',
            'contact_email' => 'sometimes|nullable|email',
            'contact_website' => 'sometimes|nullable|url|max:500',
            'contact_box_message' => 'sometimes|nullable|string|max:1000',
            'contact_box_design_message' => 'sometimes|nullable|string|max:10000',
            'facebook_url' => 'sometimes|nullable|url|max:500',
            'instagram_url' => 'sometimes|nullable|url|max:500',
            'tiktok_url' => 'sometimes|nullable|url|max:500',
            'opening_hours' => 'sometimes|nullable|array',
            'show_upcoming_events' => 'sometimes|nullable|boolean',
            'show_past_events' => 'sometimes|nullable|boolean',
            'is_approved' => 'sometimes|nullable|boolean',
        ];

        if ($this->hasFile('image_path')) {
            $rules['image_path'] = 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120';
        } else {
            $rules['image_path'] = 'sometimes|nullable|string';
        }

        $rules['additional_images'] = 'sometimes|nullable|array|max:10';
        if ($this->hasFile('additional_images')) {
            $rules['additional_images.*'] = 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120';
        } else {
            $rules['additional_images.*'] = 'nullable|string';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.min' => 'Venue title must be at least 3 characters',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'image_path.image' => 'File must be an image',
            'image_path.max' => 'Image must not exceed 5MB',
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
