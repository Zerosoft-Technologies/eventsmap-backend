<?php

namespace App\Http\Requests\V2;

use App\Models\EventV2;
use App\Models\SubCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPremium = $this->input('event_type') === 'premium';

        $rules = [
            'title' => 'required|string|min:3|max:255',
            'event_type' => ['required', 'string', Rule::in(['free', 'premium'])],
            'category_id' => 'required|integer|exists:categories,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required',
            'end_time' => 'required',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'dress_code' => 'nullable|string',
            'age_limit' => 'nullable',
            'entrance_status' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'venue_id' => 'nullable|integer|exists:venues,id',
            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'exists:subcategories,id',
            'invited_talents' => 'nullable|array',
            'invited_talents.*' => 'integer',
            'is_recurring' => 'nullable|boolean',
            'is_copy_event' => 'nullable|boolean',
            'show_upcoming_events' => 'nullable|boolean',
            'show_past_events' => 'nullable|boolean',
        ];

        if ($isPremium) {
            $rules['subcategory_ids'] = ['nullable', 'array'];
            $rules['subcategory_ids.*'] = 'exists:subcategories,id';

            $rules['entrance_fee'] = 'nullable|numeric|min:0';
            $rules['contact_phone'] = 'nullable|string|max:50';
            $rules['contact_email'] = 'nullable|email';
            $rules['contact_website'] = 'nullable|url|max:500';
            $rules['description'] = 'nullable|string|max:10000';
            $rules['contact_box_message'] = 'nullable|string|max:1000';
            $rules['venue_details'] = 'nullable|string|max:2000';
            $rules['facebook_url'] = 'nullable|url|max:500';
            $rules['instagram_url'] = 'nullable|url|max:500';
            $rules['tiktok_url'] = 'nullable|url|max:500';
            $rules['ticket_url'] = 'nullable|url|max:500';
            $rules['booking_instructions'] = 'nullable|string|max:2000';
            $rules['event_option'] = 'nullable|string|max:255';
            $rules['condition_entrance_fee'] = 'nullable';
            $rules['condition_dress_code'] = 'nullable|string|max:255';
            $rules['condition_age_limit'] = 'nullable|string|max:100';
            $rules['additional_images'] = 'nullable|array|max:10';
            $rules['additional_images.*'] = 'image|mimes:jpg,jpeg,png,webp|max:2048';
            $rules['invited_talents'] = 'nullable|array|max:20';
            $rules['invited_talents.*'] = 'integer';
            $rules['invited_organisers'] = 'nullable|array|max:20';
            $rules['invited_organisers.*'] = 'integer';
            $rules['invited_venues'] = 'nullable|array|max:20';
            $rules['invited_venues.*'] = 'integer';

            $rules['organiser_ids'] = 'nullable|array';
            $rules['organiser_ids.*'] = 'integer|exists:users,id';
            $rules['talent_ids'] = 'nullable|array';
            $rules['talent_ids.*'] = 'integer|exists:talents,id';
        } else {
            $rules['subcategory_ids'] = ['nullable', 'array'];
            $rules['subcategory_ids.*'] = 'exists:subcategories,id';

            $rules['organiser_ids'] = 'nullable|array|max:0';
            $rules['talent_ids'] = 'nullable|array|max:0';
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('subcategory_ids') && $this->filled('category_id')) {
                $validCount = SubCategory::where('category_id', $this->input('category_id'))
                    ->whereIn('id', $this->input('subcategory_ids'))
                    ->count();

                if ($validCount !== count($this->input('subcategory_ids'))) {
                    $validator->errors()->add('subcategory_ids', 'All subcategories must belong to the selected category');
                }
            }

            if ($this->filled('start_time') && $this->filled('end_time') && $this->filled('start_date')) {
                $start = \Carbon\Carbon::parse($this->input('start_date') . ' ' . $this->input('start_time'));
                $end = \Carbon\Carbon::parse(($this->input('end_date') ?? $this->input('start_date')) . ' ' . $this->input('end_time'));

                if ($end->lte($start)) {
                    $end->addDay();
                }

                $durationHours = $start->diffInMinutes($end) / 60;
                if ($durationHours > 24) {
                    $validator->errors()->add('end_time', 'Event duration cannot exceed 24 hours');
                }
            }

            if ($this->filled('latitude') xor $this->filled('longitude')) {
                $validator->errors()->add('latitude', 'Latitude and longitude must be provided together');
            }
        });
    }

    public function messages(): array
    {
        $isPremium = $this->input('event_type') === 'premium';

        $messages = [
            'title.required' => 'Event title is required',
            'title.min' => 'Event title must be at least 3 characters',
            'title.max' => 'Event title cannot exceed 255 characters',
            'event_type.required' => 'Event type is required',
            'event_type.in' => 'Event type must be free or premium',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'start_date.required' => 'Start date is required',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'start_datetime.required' => 'Start datetime is required',
            'end_datetime.required' => 'End datetime is required',
            'end_datetime.after' => 'End datetime must be after start datetime',
            'start_time.required' => 'Start time is required',
            'start_time.date_format' => 'Start time must be in HH:MM format',
            'end_time.required' => 'End time is required',
            'end_time.date_format' => 'End time must be in HH:MM format',
            'address.required' => 'Address is required',
            'latitude.required' => 'Latitude is required',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.required' => 'Longitude is required',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'image.image' => 'File must be an image',
            'image.max' => 'Image must not exceed 2MB',
            'image.mimes' => 'Image must be jpg, jpeg, png, or webp format',
        ];

        if ($isPremium) {
            $messages['subcategory_ids.required'] = 'Premium events require at least 1 subcategory';
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
