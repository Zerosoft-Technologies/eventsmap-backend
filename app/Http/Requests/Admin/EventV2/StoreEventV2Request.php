<?php

namespace App\Http\Requests\Admin\EventV2;

use App\Models\EventV2;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreEventV2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->title ?? $this->name,
            'is_recurring' => filter_var($this->is_recurring, FILTER_VALIDATE_BOOLEAN),
            'is_copy_event' => filter_var($this->is_copy_event, FILTER_VALIDATE_BOOLEAN),
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
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'integer|exists:subcategories,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required',
            'end_time' => 'required',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'address' => 'required|string|max:500',
            'venue_name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'dress_code' => ['nullable', 'string', Rule::in(EventV2::DRESS_CODES)],
            'age_limit' => ['nullable', Rule::in(EventV2::AGE_LIMITS)],
            'entrance_status' => ['nullable', 'string', Rule::in(EventV2::ENTRANCE_STATUSES)],
            'entrance_fee' => 'nullable|numeric|min:0',
            'venue_id' => 'nullable|integer|exists:venues,id',
            'description' => 'nullable|string|max:10000',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email',
            'contact_website' => 'nullable|url|max:500',
            'contact_box_message' => 'nullable|string|max:1000',
            'venue_details' => 'nullable|string|max:2000',
            'facebook_url' => 'nullable|url|max:500',
            'instagram_url' => 'nullable|url|max:500',
            'tiktok_url' => 'nullable|url|max:500',
            'ticket_url' => 'nullable|url|max:500',
            'booking_instructions' => 'nullable|string|max:2000',
            'event_option' => 'nullable|string|max:255',
            'condition_entrance_fee' => 'nullable',
            'condition_dress_code' => 'nullable|string|max:255',
            'condition_age_limit' => 'nullable|string|max:100',
            'invited_talents' => 'nullable|array',
            'invited_talents.*' => 'integer',
            'invited_organisers' => 'nullable|array',
            'invited_organisers.*' => 'integer',
            'invited_venues' => 'nullable|array',
            'invited_venues.*' => 'integer',
            'is_recurring' => 'nullable|boolean',
            'is_copy_event' => 'nullable|boolean',
            'show_upcoming_events' => 'nullable|boolean',
            'show_past_events' => 'nullable|boolean',
            'status' => ['nullable', 'string', Rule::in(EventV2::STATUSES)],
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
            'title.required' => 'Event title is required',
            'title.min' => 'Event title must be at least 3 characters',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'start_date.required' => 'Start date is required',
            'start_datetime.required' => 'Start datetime is required',
            'end_datetime.required' => 'End datetime is required',
            'end_datetime.after' => 'End datetime must be after start datetime',
            'address.required' => 'Address is required',
            'latitude.required' => 'Latitude is required',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.required' => 'Longitude is required',
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
