<?php

namespace App\Http\Requests\Admin\VenueV2;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreVenueV2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->title ?? $this->name,
            'show_upcoming_events' => filter_var($this->show_upcoming_events, FILTER_VALIDATE_BOOLEAN),
            'show_past_events' => filter_var($this->show_past_events, FILTER_VALIDATE_BOOLEAN),
            'is_approved' => filter_var($this->is_approved, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'user_id' => 'required|integer|exists:users,id',
            'title' => 'required|string|min:3|max:255',
            'event_type' => 'nullable|string|max:50',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'integer|exists:subcategories,id',
            'venue_category_id' => 'nullable|integer|exists:venue_categories,id',
            'venue_subcategory_ids' => 'nullable|array',
            'venue_subcategory_ids.*' => 'integer|exists:venue_subcategories,id',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'description' => 'nullable|string|max:10000',
            'description_items' => 'nullable|array',
            'description_items.*' => 'string|max:500',
            'allow_dogs' => 'nullable|boolean',
            'allowance_of_dogs' => 'nullable|string|max:500',
            'wheelchair_accessible' => 'nullable|boolean',
            'accessibility_description' => 'nullable|string|max:1000',
            'parking' => 'nullable|boolean',
            'valet' => 'nullable|boolean',
            'play_area' => 'nullable|boolean',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email',
            'contact_website' => 'nullable|url|max:500',
            'contact_box_message' => 'nullable|string|max:1000',
            'contact_box_design_message' => 'nullable|string|max:10000',
            'facebook_url' => 'nullable|url|max:500',
            'instagram_url' => 'nullable|url|max:500',
            'tiktok_url' => 'nullable|url|max:500',
            'opening_hours' => 'nullable|array',
            'show_upcoming_events' => 'nullable|boolean',
            'show_past_events' => 'nullable|boolean',
            'is_approved' => 'nullable|boolean',
        ];

        if ($this->hasFile('image_path')) {
            $rules['image_path'] = 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120';
        } else {
            $rules['image_path'] = 'nullable|string';
        }

        $rules['additional_images'] = 'nullable|array|max:10';
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
            'user_id.required' => 'Owner user ID is required',
            'user_id.exists' => 'Selected user does not exist',
            'title.required' => 'Venue title is required',
            'title.min' => 'Venue title must be at least 3 characters',
            'address.required' => 'Address is required',
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
