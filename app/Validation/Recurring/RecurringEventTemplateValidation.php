<?php

namespace App\Validation\Recurring;

use App\Support\PublishStatus;
use Illuminate\Validation\Rule;

/**
 * Validation for event_template stored inside recurrence_rules JSON.
 *
 * Mirrors StoreEventRequest fields needed to call EventService::create().
 */
final class RecurringEventTemplateValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = 'event_template'): array
    {
        $key = static fn (string $field) => "{$prefix}.{$field}";

        return [
            $key('title') => ['required', 'string', 'min:3', 'max:255'],
            $key('event_type') => ['required', 'string', Rule::in(['free', 'premium'])],
            $key('category_id') => ['required', 'integer', 'exists:categories,id'],
            $key('address') => ['required', 'string', 'max:500'],
            $key('venue_name') => ['nullable', 'string', 'max:255'],
            $key('latitude') => ['required', 'numeric', 'between:-90,90'],
            $key('longitude') => ['required', 'numeric', 'between:-180,180'],
            $key('start_time') => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            $key('end_time') => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            $key('dress_code') => ['nullable', 'string'],
            $key('age_limit') => ['nullable'],
            $key('entrance_status') => ['nullable', 'string'],
            $key('entrance_fee') => ['nullable', 'numeric', 'min:0'],
            $key('venue_id') => ['nullable', 'integer', 'exists:venues,id'],
            $key('subcategory_ids') => ['nullable', 'array'],
            $key('subcategory_ids.*') => ['integer', 'exists:subcategories,id'],
            $key('description') => ['nullable', 'string'],
            $key('contact_phone') => ['nullable', 'string', 'max:50'],
            $key('contact_email') => ['nullable', 'email'],
            $key('contact_website') => ['nullable', 'string', 'max:255'],
            $key('contact_box_message') => ['nullable', 'string'],
            $key('contact_box_design_message') => ['nullable', 'string'],
            $key('show_contact_box') => ['nullable', 'boolean'],
            $key('facebook_url') => ['nullable', 'string', 'max:255'],
            $key('instagram_url') => ['nullable', 'string', 'max:255'],
            $key('tiktok_url') => ['nullable', 'string', 'max:255'],
            $key('ticket_url') => ['nullable', 'string', 'max:255'],
            $key('booking_instructions') => ['nullable', 'string'],
            $key('event_option') => ['nullable', 'string'],
            $key('condition_entrance_fee') => ['nullable', 'string'],
            $key('condition_dress_code') => ['nullable', 'string'],
            $key('condition_age_limit') => ['nullable', 'string'],
            $key('venue_details') => ['nullable', 'string'],
            $key('image_path') => ['nullable', 'string'],
            $key('additional_images') => ['nullable', 'array'],
            $key('additional_images.*') => ['string'],
            $key('organiser_ids') => ['nullable', 'array'],
            $key('organiser_ids.*') => ['integer'],
            $key('talent_ids') => ['nullable', 'array'],
            $key('talent_ids.*') => ['integer'],
            $key('invited_talents') => ['nullable', 'array', 'max:20'],
            $key('invited_talents.*') => ['integer'],
            $key('invited_organisers') => ['nullable', 'array', 'max:20'],
            $key('invited_organisers.*') => ['integer'],
            $key('invited_venues') => ['nullable', 'array', 'max:20'],
            $key('invited_venues.*') => ['integer'],
            $key('is_copy_event') => ['nullable', 'boolean'],
            $key('show_upcoming_events') => ['nullable', 'boolean'],
            $key('show_past_events') => ['nullable', 'boolean'],
            $key('show_photo_map_marker') => ['nullable', 'boolean'],
            $key('publish_status') => ['nullable', 'string', Rule::in(PublishStatus::ALL)],
            $key('is_approved') => ['nullable', 'boolean'],
        ];
    }

    /**
     * Partial update rules (event_template optional).
     *
     * @return array<string, mixed>
     */
    public static function updateRules(string $prefix = 'event_template'): array
    {
        $rules = self::rules($prefix);
        $updated = [];

        foreach ($rules as $key => $rule) {
            $filtered = array_values(array_filter(
                is_array($rule) ? $rule : [$rule],
                static fn ($r) => $r !== 'required'
            ));
            array_unshift($filtered, 'sometimes');
            $updated[$key] = $filtered;
        }

        $updated[$prefix] = ['sometimes', 'array'];

        return $updated;
    }
}
