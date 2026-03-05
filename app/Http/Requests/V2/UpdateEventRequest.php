<?php

namespace App\Http\Requests\V2;

use App\Models\EventV2;
use App\Models\SubCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $event = EventV2::find($this->route('id'));
        $eventType = $this->input('event_type', $event?->event_type ?? 'free');
        $isPremium = $eventType === 'premium';

        $rules = [
            'title' => 'sometimes|required|string|min:3|max:255',
            'event_type' => ['sometimes', 'required', 'string', Rule::in(['free', 'premium'])],
            'category_id' => 'sometimes|required|integer|exists:categories,id',
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
        ];

        if ($isPremium) {
            $rules['subcategory_ids'] = ['sometimes', 'required', 'array', 'min:' . EventV2::PREMIUM_MIN_SUBCATEGORIES];
            $rules['subcategory_ids.*'] = 'integer|exists:subcategories,id';

            $rules['entrance_fee'] = 'sometimes|nullable|numeric|min:0';
            $rules['contact_phone'] = 'sometimes|nullable|string|max:50';
            $rules['contact_email'] = 'sometimes|nullable|email';
            $rules['contact_website'] = 'sometimes|nullable|url|max:500';
            $rules['description'] = 'sometimes|nullable|string|max:10000';
            $rules['contact_box_message'] = 'sometimes|nullable|string|max:1000';
            $rules['venue_details'] = 'sometimes|nullable|string|max:2000';
            $rules['facebook_url'] = 'sometimes|nullable|url|max:500';
            $rules['instagram_url'] = 'sometimes|nullable|url|max:500';
            $rules['tiktok_url'] = 'sometimes|nullable|url|max:500';
            $rules['ticket_url'] = 'sometimes|nullable|url|max:500';
            $rules['booking_instructions'] = 'sometimes|nullable|string|max:2000';
            $rules['event_option'] = 'sometimes|nullable|string|max:255';
            $rules['condition_entrance_fee'] = 'sometimes|nullable';
            $rules['condition_dress_code'] = 'sometimes|nullable|string|max:255';
            $rules['condition_age_limit'] = 'sometimes|nullable|string|max:100';
            $rules['additional_images'] = 'sometimes|nullable|array|max:10';
            $rules['additional_images.*'] = 'image|mimes:jpg,jpeg,png,webp|max:2048';
            $rules['invited_talents'] = 'sometimes|nullable|array|max:20';
            $rules['invited_talents.*'] = 'integer';
            $rules['invited_organisers'] = 'sometimes|nullable|array|max:20';
            $rules['invited_organisers.*'] = 'integer';
            $rules['invited_venues'] = 'sometimes|nullable|array|max:20';
            $rules['invited_venues.*'] = 'integer';

            $rules['organiser_ids'] = 'sometimes|nullable|array';
            $rules['organiser_ids.*'] = 'integer|exists:users,id';
            $rules['talent_ids'] = 'sometimes|nullable|array';
            $rules['talent_ids.*'] = 'integer|exists:talents,id';
        } else {
            $rules['subcategory_ids'] = ['sometimes', 'nullable', 'array', 'max:' . EventV2::FREE_MAX_SUBCATEGORIES];
            $rules['subcategory_ids.*'] = 'integer|exists:subcategories,id';

            $rules['organiser_ids'] = 'sometimes|nullable|array|max:0';
            $rules['talent_ids'] = 'sometimes|nullable|array|max:0';
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $event = EventV2::find($this->route('id'));
            $categoryId = $this->input('category_id', $event?->category_id);

            if ($this->filled('subcategory_ids') && $categoryId) {
                $validCount = SubCategory::where('category_id', $categoryId)
                    ->whereIn('id', $this->input('subcategory_ids'))
                    ->count();

                if ($validCount !== count($this->input('subcategory_ids'))) {
                    $validator->errors()->add('subcategory_ids', 'All subcategories must belong to the selected category');
                }
            }

            $eventDate = $this->input('event_date') ?? $event?->event_date?->format('Y-m-d');
            $startTime = $this->input('start_time') ?? $event?->start_time;
            $endTime = $this->input('end_time') ?? $event?->end_time;

            if ($startTime && $endTime && $eventDate) {
                $start = \Carbon\Carbon::parse("{$eventDate} {$startTime}");
                $end = \Carbon\Carbon::parse("{$eventDate} {$endTime}");

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

    public function messages(): array
    {
        $eventType = $this->input('event_type', 'premium');
        $isPremium = $eventType === 'premium';

        $messages = [
            'title.min' => 'Event title must be at least 3 characters',
            'title.max' => 'Event title cannot exceed 255 characters',
            'category_id.exists' => 'Selected category does not exist',
            'event_date.after' => 'Event date must be in the future',
            'event_date.before_or_equal' => 'Events can be scheduled up to 1 year in advance',
            'start_time.date_format' => 'Start time must be in HH:MM format',
            'end_time.date_format' => 'End time must be in HH:MM format',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'image.image' => 'File must be an image',
            'image.max' => 'Image must not exceed 2MB',
            'image.mimes' => 'Image must be jpg, jpeg, png, or webp format',
            'dress_code.in' => 'Invalid dress code. Valid options: ' . implode(', ', EventV2::DRESS_CODES),
            'age_limit.in' => 'Invalid age limit. Valid options: ' . implode(', ', EventV2::AGE_LIMITS),
            'entrance_status.in' => 'Invalid entrance status. Valid options: ' . implode(', ', EventV2::ENTRANCE_STATUSES),
        ];

        if ($isPremium) {
            $messages['subcategory_ids.min'] = 'Premium events require at least 1 subcategory';
        } else {
            $messages['subcategory_ids.max'] = 'Free events allow a maximum of ' . EventV2::FREE_MAX_SUBCATEGORIES . ' subcategor' . (EventV2::FREE_MAX_SUBCATEGORIES === 1 ? 'y' : 'ies');
        }

        return $messages;
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
