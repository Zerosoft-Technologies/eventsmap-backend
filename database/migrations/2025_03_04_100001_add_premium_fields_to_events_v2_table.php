<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            $table->enum('event_type', ['free', 'premium'])->default('free')->after('is_free_package');
            $table->decimal('entrance_fee', 10, 2)->nullable()->after('entrance_status');
            $table->string('contact_phone')->nullable()->after('address');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('contact_website')->nullable()->after('contact_email');
            $table->text('description')->nullable()->after('contact_website');
            $table->text('contact_box_message')->nullable()->after('description');
            $table->text('venue_details')->nullable()->after('contact_box_message');
            $table->string('facebook_url')->nullable()->after('venue_details');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('tiktok_url')->nullable()->after('instagram_url');
            $table->string('ticket_url')->nullable()->after('tiktok_url');
            $table->text('booking_instructions')->nullable()->after('ticket_url');
            $table->string('event_option')->nullable()->after('booking_instructions');
            $table->boolean('condition_entrance_fee')->nullable()->after('event_option');
            $table->string('condition_dress_code')->nullable()->after('condition_entrance_fee');
            $table->string('condition_age_limit')->nullable()->after('condition_dress_code');
            $table->json('additional_images')->nullable()->after('image_path');

            $table->index('event_type', 'idx_events_v2_event_type');
            $table->index('entrance_fee', 'idx_events_v2_entrance_fee');
        });

        if (Schema::hasColumn('events_v2', 'latitude')) {
            Schema::table('events_v2', function (Blueprint $table) {
                $table->decimal('latitude', 11, 8)->change();
                $table->decimal('longitude', 11, 8)->change();
            });
        }

    }

    public function down(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            $table->dropIndex('idx_events_v2_event_type');
            $table->dropIndex('idx_events_v2_entrance_fee');

            $table->dropColumn([
                'event_type',
                'entrance_fee',
                'contact_phone',
                'contact_email',
                'contact_website',
                'description',
                'contact_box_message',
                'venue_details',
                'facebook_url',
                'instagram_url',
                'tiktok_url',
                'ticket_url',
                'booking_instructions',
                'event_option',
                'condition_entrance_fee',
                'condition_dress_code',
                'condition_age_limit',
                'additional_images',
            ]);
        });

        Schema::table('events_v2', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->change();
            $table->decimal('longitude', 10, 7)->change();
        });
    }
};
