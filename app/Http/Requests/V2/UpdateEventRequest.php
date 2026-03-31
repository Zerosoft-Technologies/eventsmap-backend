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
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'dress_code' => 'sometimes|nullable|string',
            'age_limit' => 'nullable',
            'entrance_status' => 'nullable|string',
            'image_path' => 'nullable|string',
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
            $rules['additional_images.*'] = 'sometimes|nullable|string';
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
            $rules['subcategory_ids'] = ['nullable', 'array'];
            $rules['subcategory_ids.*'] = 'exists:subcategories,id';

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

            // Validate main image UUID
            if ($this->filled('image_path')) {
                $user = $this->user();
                $imageId = $this->input('image_path');
                
                // Check if it's a UUID format
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imageId)) {
                    $validator->errors()->add('image_path', 'Invalid image ID format');
                } else {
                    // Check if gallery image exists and belongs to user
                    $galleryImage = \App\Models\GalleryImage::where('image_id', $imageId)
                        ->where('user_id', $user->id)
                        ->where('is_deleted', false)
                        ->first();

                    if (!$galleryImage) {
                        $validator->errors()->add('image_path', 'Image not found or does not belong to you');
                    }
                }
            }

            // Validate additional_images UUIDs
            if ($this->filled('additional_images') && is_array($this->input('additional_images'))) {
                $user = $this->user();
                foreach ($this->input('additional_images') as $index => $imageId) {
                    if (!empty($imageId)) {
                        // Check if it's a UUID format
                        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imageId)) {
                            $validator->errors()->add("additional_images.{$index}", 'Invalid image ID format');
                            continue;
                        }

                        // Check if gallery image exists and belongs to user
                        $galleryImage = \App\Models\GalleryImage::where('image_id', $imageId)
                            ->where('user_id', $user->id)
                            ->where('is_deleted', false)
                            ->first();

                        if (!$galleryImage) {
                            $validator->errors()->add("additional_images.{$index}", 'Image not found or does not belong to you');
                        }
                    }
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
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'end_datetime.after' => 'End datetime must be after start datetime',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'image.image' => 'File must be an image',
            'image.max' => 'Image must not exceed 2MB',
            'image.mimes' => 'Image must be jpg, jpeg, png, or webp format',
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
