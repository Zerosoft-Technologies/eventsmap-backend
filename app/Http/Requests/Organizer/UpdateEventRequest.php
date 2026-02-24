<?php

namespace App\Http\Requests\Organizer;

use Illuminate\Foundation\Http\FormRequest;

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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:3|max:200',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|unique:events,slug,' . $this->route('id'),
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'dresscode' => 'nullable|string|max:100',
            'age_restriction' => 'nullable|string|max:200',
            'min_age' => 'nullable|integer|min:0',
            'max_age' => 'nullable|integer|min:0',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string|max:50',
            'is_all_day' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'venue_name' => 'nullable|string|max:200',
            'venue_address' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'organizer_name' => 'nullable|string|max:200',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_info' => 'nullable|array',
            'cover_image' => 'nullable|string|max:500',
            'video_url' => 'nullable|string|max:500',
            'about' => 'nullable|array',
            'location_details' => 'nullable|array',
            'booking' => 'nullable|array',
            'social_links' => 'nullable|array',
            'highlights' => 'nullable|array',
            'requirements' => 'nullable|array',
            'additional_info' => 'nullable|string',
            'accessibility_info' => 'nullable|string',
            'is_ticketed' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'capacity' => 'nullable|integer|min:0',
            'registration_url' => 'nullable|url|max:500',
            'registration_deadline' => 'nullable|date',
            'meta_keywords' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'tags' => 'nullable|array',
            'morning' => 'nullable|boolean',
            'afternoon' => 'nullable|boolean',
            'evening' => 'nullable|boolean',
            'night' => 'nullable|boolean',
        ];
    }
}
