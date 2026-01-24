<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add missing event fields for proper API support.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Only add columns that don't exist
            if (!Schema::hasColumn('events', 'status')) {
                $table->string('status')->default('draft')->after('slug');
            }
            
            if (!Schema::hasColumn('events', 'short_description')) {
                $table->text('short_description')->nullable()->after('description');
            }
            
            if (!Schema::hasColumn('events', 'highlights')) {
                $table->json('highlights')->nullable()->after('tags');
            }
            
            if (!Schema::hasColumn('events', 'requirements')) {
                $table->json('requirements')->nullable()->after('highlights');
            }
            
            if (!Schema::hasColumn('events', 'additional_info')) {
                $table->json('additional_info')->nullable()->after('requirements');
            }
            
            if (!Schema::hasColumn('events', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('additional_info');
            }
            
            if (!Schema::hasColumn('events', 'state')) {
                $table->string('state', 100)->nullable()->after('country');
            }
            
            if (!Schema::hasColumn('events', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('state');
            }
            
            if (!Schema::hasColumn('events', 'is_all_day')) {
                $table->boolean('is_all_day')->default(false)->after('timezone');
            }
            
            if (!Schema::hasColumn('events', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('is_all_day');
            }
            
            if (!Schema::hasColumn('events', 'is_ticketed')) {
                $table->boolean('is_ticketed')->default(false)->after('is_recurring');
            }
            
            if (!Schema::hasColumn('events', 'is_free')) {
                $table->boolean('is_free')->default(false)->after('is_ticketed');
            }
            
            if (!Schema::hasColumn('events', 'age_restriction')) {
                $table->text('age_restriction')->nullable()->after('dresscode');
            }
            
            if (!Schema::hasColumn('events', 'accessibility_info')) {
                $table->text('accessibility_info')->nullable()->after('age_restriction');
            }
            
            if (!Schema::hasColumn('events', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('view_count');
            }
            
            if (!Schema::hasColumn('events', 'featured_at')) {
                $table->timestamp('featured_at')->nullable()->after('published_at');
            }
            
            if (!Schema::hasColumn('events', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('featured_at');
            }
            
            if (!Schema::hasColumn('events', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('cancelled_at');
            }
            
            // Only add indexes that don't exist
            if (!Schema::hasIndex('events', 'events_status_index')) {
                $table->index('status');
            }
            
            if (!Schema::hasIndex('events', 'events_is_all_day_is_recurring_index')) {
                $table->index(['is_all_day', 'is_recurring']);
            }
            
            if (!Schema::hasIndex('events', 'events_is_ticketed_is_free_index')) {
                $table->index(['is_ticketed', 'is_free']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['is_all_day', 'is_recurring']);
            $table->dropIndex(['is_ticketed', 'is_free']);
            
            $table->dropColumn([
                'status',
                'short_description',
                'highlights',
                'requirements',
                'additional_info',
                'custom_fields',
                'state',
                'postal_code',
                'is_all_day',
                'is_recurring',
                'is_ticketed',
                'is_free',
                'age_restriction',
                'accessibility_info',
                'published_at',
                'featured_at',
                'cancelled_at',
                'archived_at',
            ]);
        });
    }
};
