<?php

namespace App\Http\Requests\V2;

use App\Models\EventV2;
use App\Models\SubCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * StoreEventRequest - Validation for creating V2 events.
 *
 * Validates all required fields for event creation including:
 * - Basic event info (title, category, dates)
 * - Location data (address, coordinates)
 * - Event settings (dress code, age limit, entrance status)
 * - Relationships (subcategories, organisers, talents)
 * - Image upload with security validation
 */
class StoreEventRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:3|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_ids' => 'nullable|array|max:' . EventV2::FREE_MAX_SUBCATEGORIES,
            'subcategory_ids.*' => 'integer|exists:subcategories,id',
            'event_date' => 'required|date|after:today|before_or_equal:' . now()->addDays(EventV2::FREE_MAX_ADVANCE_DAYS)->format('Y-m-d'),
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'dress_code' => ['required', 'string', Rule::in(EventV2::DRESS_CODES)],
            'age_limit' => ['required', 'string', Rule::in(EventV2::AGE_LIMITS)],
            'entrance_status' => ['required', 'string', Rule::in(EventV2::ENTRANCE_STATUSES)],
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'venue_id' => 'nullable|integer|exists:venues,id',
            'organiser_ids' => 'nullable|array',
            'organiser_ids.*' => 'integer|exists:users,id',
            'talent_ids' => 'nullable|array',
            'talent_ids.*' => 'integer|exists:talents,id',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate subcategories belong to the selected category
            if ($this->filled('subcategory_ids') && $this->filled('category_id')) {
                $validCount = SubCategory::where('category_id', $this->input('category_id'))
                    ->whereIn('id', $this->input('subcategory_ids'))
                    ->count();

                if ($validCount !== count($this->input('subcategory_ids'))) {
                    $validator->errors()->add('subcategory_ids', 'All subcategories must belong to the selected category');
                }
            }

            // Validate event duration (max 24h, supports overnight events)
            if ($this->filled('start_time') && $this->filled('end_time') && $this->filled('event_date')) {
                $start = \Carbon\Carbon::parse($this->input('event_date') . ' ' . $this->input('start_time'));
                $end = \Carbon\Carbon::parse($this->input('event_date') . ' ' . $this->input('end_time'));

                // If end is before or equal to start, it's an overnight event (spans to next day)
                if ($end->lte($start)) {
                    $end->addDay();
                }

                $durationHours = $start->diffInMinutes($end) / 60;
                if ($durationHours > 24) {
                    $validator->errors()->add('end_time', 'Event duration cannot exceed 24 hours');
                }
            }

            // Validate latitude and longitude are provided together
            if ($this->filled('latitude') xor $this->filled('longitude')) {
                $validator->errors()->add('latitude', 'Latitude and longitude must be provided together');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required',
            'title.min' => 'Event title must be at least 3 characters',
            'title.max' => 'Event title cannot exceed 255 characters',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'event_date.required' => 'Event date is required',
            'event_date.after' => 'Event date must be in the future',
            'event_date.before_or_equal' => 'Free package events can be scheduled up to 1 year in advance',
            'start_time.required' => 'Start time is required',
            'start_time.date_format' => 'Start time must be in HH:MM format',
            'end_time.required' => 'End time is required',
            'end_time.date_format' => 'End time must be in HH:MM format',
            'address.required' => 'Address is required',
            'latitude.required' => 'Latitude is required',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.required' => 'Longitude is required',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'subcategory_ids.max' => 'Free package allows a maximum of ' . EventV2::FREE_MAX_SUBCATEGORIES . ' subcategories',
            'image.image' => 'File must be an image',
            'image.max' => 'Image must not exceed 2MB',
            'image.mimes' => 'Image must be jpg, jpeg, png, or webp format',
            'dress_code.in' => 'Invalid dress code. Valid options: ' . implode(', ', EventV2::DRESS_CODES),
            'age_limit.in' => 'Invalid age limit. Valid options: ' . implode(', ', EventV2::AGE_LIMITS),
            'entrance_status.in' => 'Invalid entrance status. Valid options: ' . implode(', ', EventV2::ENTRANCE_STATUSES),
        ];
    }

    /**
     * Handle a failed validation attempt.
     * Returns standardized JSON error response.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
