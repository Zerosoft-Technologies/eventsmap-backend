<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends events table with additional fields for event details page.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Slug for SEO-friendly URLs
            $table->string('slug')->unique()->nullable()->after('title');

            // Pricing fields
            $table->decimal('min_price', 10, 2)->nullable()->after('price');
            $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
            $table->string('currency', 3)->default('USD')->after('max_price');

            // Timezone
            $table->string('timezone', 50)->default('UTC')->after('end_datetime');

            // Venue details
            $table->string('venue_name')->nullable()->after('address');
            $table->string('country', 100)->nullable()->after('city');

            // Age restrictions
            $table->integer('max_age')->nullable()->after('min_age');

            // Organizer information
            $table->string('organizer_name')->nullable()->after('max_age');
            $table->foreignId('organizer_id')->nullable()->after('organizer_name');
            $table->json('contact_info')->nullable()->after('organizer_id');

            // Media
            $table->string('cover_image')->nullable()->after('contact_info');
            $table->string('video_url')->nullable()->after('cover_image');

            // About section (JSON for flexibility)
            $table->json('about')->nullable()->after('video_url');

            // Location details (JSON)
            $table->json('location_details')->nullable()->after('about');

            // Booking information (JSON)
            $table->json('booking')->nullable()->after('location_details');

            // Social links
            $table->json('social_links')->nullable()->after('booking');

            // Status flags
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->boolean('is_cancelled')->default(false)->after('is_featured');

            // SEO meta
            $table->string('meta_title')->nullable()->after('is_cancelled');
            $table->text('meta_description')->nullable()->after('meta_title');

            // Tags (stored as JSON array)
            $table->json('tags')->nullable()->after('meta_description');

            // Analytics
            $table->unsignedBigInteger('view_count')->default(0)->after('tags');

            // Indexes for common queries
            $table->index('slug');
            $table->index('is_featured');
            $table->index('is_cancelled');
            $table->index('organizer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['is_cancelled']);
            $table->dropIndex(['organizer_id']);

            $table->dropColumn([
                'slug',
                'min_price',
                'max_price',
                'currency',
                'timezone',
                'venue_name',
                'country',
                'max_age',
                'organizer_name',
                'organizer_id',
                'contact_info',
                'cover_image',
                'video_url',
                'about',
                'location_details',
                'booking',
                'social_links',
                'is_featured',
                'is_cancelled',
                'meta_title',
                'meta_description',
                'tags',
                'view_count',
            ]);
        });
    }
};
