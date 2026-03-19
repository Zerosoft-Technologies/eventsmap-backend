<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events_v2')) {
            Schema::table('events_v2', function (Blueprint $table) {
                if (!Schema::hasColumn('events_v2', 'start_date')) {
                    $table->date('start_date')->nullable()->after('event_date');
                }
                if (!Schema::hasColumn('events_v2', 'end_date')) {
                    $table->date('end_date')->nullable()->after('start_date');
                }
                if (!Schema::hasColumn('events_v2', 'start_datetime')) {
                    $table->dateTime('start_datetime')->nullable()->after('end_date');
                }
                if (!Schema::hasColumn('events_v2', 'end_datetime')) {
                    $table->dateTime('end_datetime')->nullable()->after('start_datetime');
                }
                if (!Schema::hasColumn('events_v2', 'is_recurring')) {
                    $table->boolean('is_recurring')->default(false)->after('booking_instructions');
                }
                if (!Schema::hasColumn('events_v2', 'is_copy_event')) {
                    $table->boolean('is_copy_event')->default(false)->after('is_recurring');
                }
                if (!Schema::hasColumn('events_v2', 'show_upcoming_events')) {
                    $table->boolean('show_upcoming_events')->default(false)->after('is_copy_event');
                }
                if (!Schema::hasColumn('events_v2', 'show_past_events')) {
                    $table->boolean('show_past_events')->default(false)->after('show_upcoming_events');
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'event_type')) {
                    $table->string('event_type')->nullable()->after('title');
                }
                if (!Schema::hasColumn('events', 'start_date')) {
                    $table->date('start_date')->nullable()->after('start_datetime');
                }
                if (!Schema::hasColumn('events', 'end_date')) {
                    $table->date('end_date')->nullable()->after('start_date');
                }
                if (!Schema::hasColumn('events', 'start_time')) {
                    $table->time('start_time')->nullable()->after('end_date');
                }
                if (!Schema::hasColumn('events', 'end_time')) {
                    $table->time('end_time')->nullable()->after('start_time');
                }
                if (!Schema::hasColumn('events', 'dress_code')) {
                    $table->string('dress_code')->nullable()->after('dresscode');
                }
                if (!Schema::hasColumn('events', 'age_limit')) {
                    $table->string('age_limit')->nullable()->after('dress_code');
                }
                if (!Schema::hasColumn('events', 'entrance_status')) {
                    $table->string('entrance_status')->nullable()->after('age_limit');
                }
                if (!Schema::hasColumn('events', 'contact_phone')) {
                    $table->string('contact_phone')->nullable()->after('organizer_id');
                }
                if (!Schema::hasColumn('events', 'contact_email')) {
                    $table->string('contact_email')->nullable()->after('contact_phone');
                }
                if (!Schema::hasColumn('events', 'contact_website')) {
                    $table->string('contact_website')->nullable()->after('contact_email');
                }
                if (!Schema::hasColumn('events', 'contact_box_message')) {
                    $table->text('contact_box_message')->nullable()->after('contact_website');
                }
                if (!Schema::hasColumn('events', 'facebook_url')) {
                    $table->string('facebook_url')->nullable()->after('contact_box_message');
                }
                if (!Schema::hasColumn('events', 'instagram_url')) {
                    $table->string('instagram_url')->nullable()->after('facebook_url');
                }
                if (!Schema::hasColumn('events', 'tiktok_url')) {
                    $table->string('tiktok_url')->nullable()->after('instagram_url');
                }
                if (!Schema::hasColumn('events', 'ticket_url')) {
                    $table->string('ticket_url')->nullable()->after('tiktok_url');
                }
                if (!Schema::hasColumn('events', 'booking_instructions')) {
                    $table->text('booking_instructions')->nullable()->after('ticket_url');
                }
                if (!Schema::hasColumn('events', 'is_copy_event')) {
                    $table->boolean('is_copy_event')->default(false)->after('is_recurring');
                }
                if (!Schema::hasColumn('events', 'show_upcoming_events')) {
                    $table->boolean('show_upcoming_events')->default(false)->after('is_copy_event');
                }
                if (!Schema::hasColumn('events', 'show_past_events')) {
                    $table->boolean('show_past_events')->default(false)->after('show_upcoming_events');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events_v2')) {
            Schema::table('events_v2', function (Blueprint $table) {
                $drop = [];
                foreach (['start_date', 'end_date', 'start_datetime', 'end_datetime', 'is_recurring', 'is_copy_event', 'show_upcoming_events', 'show_past_events'] as $column) {
                    if (Schema::hasColumn('events_v2', $column)) {
                        $drop[] = $column;
                    }
                }
                if (!empty($drop)) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                $drop = [];
                foreach ([
                    'event_type', 'start_date', 'end_date', 'start_time', 'end_time',
                    'dress_code', 'age_limit', 'entrance_status', 'contact_phone',
                    'contact_email', 'contact_website', 'contact_box_message',
                    'facebook_url', 'instagram_url', 'tiktok_url', 'ticket_url',
                    'booking_instructions', 'is_copy_event', 'show_upcoming_events', 'show_past_events',
                ] as $column) {
                    if (Schema::hasColumn('events', $column)) {
                        $drop[] = $column;
                    }
                }
                if (!empty($drop)) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
