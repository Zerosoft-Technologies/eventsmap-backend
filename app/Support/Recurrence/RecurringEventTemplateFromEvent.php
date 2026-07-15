<?php

namespace App\Support\Recurrence;

use App\Models\EventV2;
use App\Support\PublishStatus;

/**
 * Builds recurrence event_template JSON from an existing events_v2 row.
 */
final class RecurringEventTemplateFromEvent
{
    /**
     * @return array<string, mixed>
     */
    public static function extract(EventV2 $event): array
    {
        $event->loadMissing(['organisers', 'talents']);

        $template = [
            'title' => $event->title,
            'event_type' => $event->event_type ?? 'premium',
            'category_id' => (int) $event->category_id,
            'address' => (string) $event->address,
            'venue_name' => $event->venue_name,
            'latitude' => (float) $event->latitude,
            'longitude' => (float) $event->longitude,
            'start_time' => self::normalizeTime($event->start_time),
            'end_time' => self::normalizeTime($event->end_time),
            'description' => $event->description,
            'dress_code' => $event->dress_code,
            'age_limit' => $event->age_limit,
            'entrance_status' => $event->entrance_status,
            'entrance_fee' => $event->entrance_fee,
            'venue_id' => $event->venue_id,
            'venue_details' => $event->venue_details,
            'contact_phone' => $event->contact_phone,
            'contact_email' => $event->contact_email,
            'contact_website' => $event->contact_website,
            'contact_box_message' => $event->contact_box_message,
            'contact_box_design_message' => $event->contact_box_design_message,
            'show_contact_box' => (bool) ($event->show_contact_box ?? false),
            'facebook_url' => $event->facebook_url,
            'instagram_url' => $event->instagram_url,
            'tiktok_url' => $event->tiktok_url,
            'ticket_url' => $event->ticket_url,
            'booking_instructions' => $event->booking_instructions,
            'event_option' => $event->event_option,
            'condition_entrance_fee' => $event->condition_entrance_fee,
            'condition_dress_code' => $event->condition_dress_code,
            'condition_age_limit' => $event->condition_age_limit,
            'image_path' => $event->image_path,
            'additional_images' => $event->additional_images,
            'show_upcoming_events' => (bool) ($event->show_upcoming_events ?? true),
            'show_past_events' => (bool) ($event->show_past_events ?? false),
            'show_photo_map_marker' => (bool) ($event->show_photo_map_marker ?? false),
            'publish_status' => $event->publish_status ?? PublishStatus::DRAFT,
            'is_approved' => false,
            'is_copy_event' => false,
            'is_recurring' => true,
        ];

        if (is_array($event->subcategory_ids) && $event->subcategory_ids !== []) {
            $template['subcategory_ids'] = array_values(array_map('intval', $event->subcategory_ids));
        }

        $organiserIds = $event->organisers->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        if ($organiserIds !== []) {
            $template['organiser_ids'] = $organiserIds;
        }

        $talentIds = $event->talents->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        if ($talentIds !== []) {
            $template['talent_ids'] = $talentIds;
        }

        $invitedTalents = $event->invited_talents ?? [];
        if (is_array($invitedTalents) && $invitedTalents !== []) {
            $template['invited_talents'] = array_values(array_map('intval', $invitedTalents));
        }

        $invitedOrganisers = $event->invited_organisers ?? [];
        if (is_array($invitedOrganisers) && $invitedOrganisers !== []) {
            $template['invited_organisers'] = array_values(array_map('intval', $invitedOrganisers));
        }

        $invitedVenues = $event->invited_venues ?? [];
        if (is_array($invitedVenues) && $invitedVenues !== []) {
            $template['invited_venues'] = array_values(array_map('intval', $invitedVenues));
        }

        return array_filter(
            $template,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    private static function normalizeTime(?string $time): string
    {
        if ($time === null || $time === '') {
            return '18:00:00';
        }

        $parts = explode(':', $time);

        return sprintf('%02d:%02d:%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
    }
}
