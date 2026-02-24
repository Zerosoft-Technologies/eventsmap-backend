<?php

namespace App\Http\Requests\V2;

use App\Models\EventV2;
use App\Models\SubCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * UpdateEventRequest - Validation for updating V2 events.
 *
 * Similar to StoreEventRequest but with 'sometimes' rules
 * to allow partial updates.
 */
class UpdateEventRequest extends FormRequest
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
            'title' => 'sometimes|required|string|min:3|max:255',
            'category_id' => 'sometimes|required|integer|exists:categories,id',
            'subcategory_ids' => 'sometimes|nullable|array|max:' . EventV2::FREE_MAX_SUBCATEGORIES,
            'subcategory_ids.*' => 'integer|exists:subcategories,id',
            'event_date' => 'sometimes|required|date|after:today|before_or_equal:' . now()->addDays(EventV2::FREE_MAX_ADVANCE_DAYS)->format('Y-m-d'),
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'address' => 'sometimes|required|string|max:500',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'dress_code' => ['sometimes', 'required', 'string', Rule::in(EventV2::DRESS_CODES)],
            'age_limit' => ['sometimes', 'required', 'string', Rule::in(EventV2::AGE_LIMITS)],
            'entrance_status' => ['sometimes', 'required', 'string', Rule::in(EventV2::ENTRANCE_STATUSES)],
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'venue_id' => 'nullable|integer|exists:venues,id',
            'organiser_ids' => 'sometimes|nullable|array',
            'organiser_ids.*' => 'integer|exists:users,id',
            'talent_ids' => 'sometimes|nullable|array',
            'talent_ids.*' => 'integer|exists:talents,id',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Get event from route for fallback values
            $event = EventV2::find($this->route('id'));

            // Validate subcategories belong to the selected category
            $categoryId = $this->input('category_id', $event?->category_id);

            if ($this->filled('subcategory_ids') && $categoryId) {
                $validCount = SubCategory::where('category_id', $categoryId)
                    ->whereIn('id', $this->input('subcategory_ids'))
                    ->count();

                if ($validCount !== count($this->input('subcategory_ids'))) {
                    $validator->errors()->add('subcategory_ids', 'All subcategories must belong to the selected category');
                }
            }

            // Validate event duration (max 24h, supports overnight events)
            $eventDate = $this->input('event_date') ?? $event?->event_date?->format('Y-m-d');
            $startTime = $this->input('start_time') ?? $event?->start_time;
            $endTime = $this->input('end_time') ?? $event?->end_time;

            if ($startTime && $endTime && $eventDate) {
                $start = \Carbon\Carbon::parse("{$eventDate} {$startTime}");
                $end = \Carbon\Carbon::parse("{$eventDate} {$endTime}");

                // If end is before or equal to start, it's an overnight event
                if ($end->lte($start)) {
                    $end->addDay();
                }

                $durationHours = $start->diffInMinutes($end) / 60;
                if ($durationHours > 24) {
                    $validator->errors()->add('end_time', 'Event duration cannot exceed 24 hours');
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.min' => 'Event title must be at least 3 characters',
            'title.max' => 'Event title cannot exceed 255 characters',
            'category_id.exists' => 'Selected category does not exist',
            'event_date.after' => 'Event date must be in the future',
            'event_date.before_or_equal' => 'Free package events can be scheduled up to 1 year in advance',
            'start_time.date_format' => 'Start time must be in HH:MM format',
            'end_time.date_format' => 'End time must be in HH:MM format',
            'latitude.between' => 'Latitude must be between -90 and 90',
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
