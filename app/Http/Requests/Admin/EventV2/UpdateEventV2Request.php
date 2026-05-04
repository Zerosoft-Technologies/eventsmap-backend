<?php

namespace App\Http\Requests\Admin\EventV2;

use App\Models\EventV2;
use App\Support\PublishStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateEventV2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_recurring')) {
            $this->merge(['is_recurring' => filter_var($this->is_recurring, FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('is_copy_event')) {
            $this->merge(['is_copy_event' => filter_var($this->is_copy_event, FILTER_VALIDATE_BOOLEAN)]);
        }
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
            'category_id' => 'sometimes|integer|exists:categories,id',
            'subcategory_ids' => 'sometimes|nullable|array',
            'subcategory_ids.*' => 'integer|exists:subcategories,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'start_time' => 'sometimes',
            'end_time' => 'sometimes',
            'start_datetime' => 'sometimes|date',
            'end_datetime' => 'sometimes|date|after:start_datetime',
            'address' => 'sometimes|string|max:500',
            'venue_name' => 'sometimes|nullable|string|max:255',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'dress_code' => ['sometimes', 'nullable', 'string', Rule::in(EventV2::DRESS_CODES)],
            'age_limit' => ['sometimes', 'nullable', Rule::in(EventV2::AGE_LIMITS)],
            'entrance_status' => ['sometimes', 'nullable', 'string', Rule::in(EventV2::ENTRANCE_STATUSES)],
            'entrance_fee' => 'sometimes|nullable|numeric|min:0',
            'venue_id' => 'sometimes|nullable|integer|exists:venues,id',
            'description' => 'sometimes|nullable|string|max:10000',
            'contact_phone' => 'sometimes|nullable|string|max:50',
            'contact_email' => 'sometimes|nullable|email',
            'contact_website' => 'sometimes|nullable|url|max:500',
            'contact_box_message' => 'sometimes|nullable|string|max:1000',
            'contact_box_design_message' => 'sometimes|nullable|string|max:10000',
            'venue_details' => 'sometimes|nullable|string|max:2000',
            'facebook_url' => 'sometimes|nullable|url|max:500',
            'instagram_url' => 'sometimes|nullable|url|max:500',
            'tiktok_url' => 'sometimes|nullable|url|max:500',
            'ticket_url' => 'sometimes|nullable|url|max:500',
            'booking_instructions' => 'sometimes|nullable|string|max:2000',
            'event_option' => 'sometimes|nullable|string|max:255',
            'condition_entrance_fee' => 'sometimes|nullable',
            'condition_dress_code' => 'sometimes|nullable|string|max:255',
            'condition_age_limit' => 'sometimes|nullable|string|max:100',
            'invited_talents' => 'sometimes|nullable|array',
            'invited_talents.*' => 'integer',
            'invited_organisers' => 'sometimes|nullable|array',
            'invited_organisers.*' => 'integer',
            'invited_venues' => 'sometimes|nullable|array',
            'invited_venues.*' => 'integer',
            'is_recurring' => 'sometimes|nullable|boolean',
            'is_copy_event' => 'sometimes|nullable|boolean',
            'show_upcoming_events' => 'sometimes|nullable|boolean',
            'show_past_events' => 'sometimes|nullable|boolean',
            'status' => ['sometimes', 'nullable', 'string', Rule::in(EventV2::STATUSES)],
            'publish_status' => ['sometimes', 'nullable', 'string', Rule::in(PublishStatus::ALL)],
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
            'title.min' => 'Event title must be at least 3 characters',
            'end_datetime.after' => 'End datetime must be after start datetime',
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
