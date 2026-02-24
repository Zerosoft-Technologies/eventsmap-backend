<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds production-grade indexes and admin moderation fields.
     */
    public function up(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            // Admin moderation fields
            $table->boolean('is_approved')->default(false)->after('is_free_package');
            $table->timestamp('approved_at')->nullable()->after('is_approved');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable()->after('approved_by');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')
                ->constrained('users')->nullOnDelete();

            // Performance indexes for public feed and filtering
            $table->index(['status', 'is_approved'], 'idx_events_v2_status_approved');
            $table->index(['event_date', 'status'], 'idx_events_v2_date_status');
            $table->index(['latitude', 'longitude'], 'idx_events_v2_coordinates');
            $table->index('is_approved', 'idx_events_v2_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            $table->dropIndex('idx_events_v2_status_approved');
            $table->dropIndex('idx_events_v2_date_status');
            $table->dropIndex('idx_events_v2_coordinates');
            $table->dropIndex('idx_events_v2_approved');

            $table->dropForeign(['approved_by']);
            $table->dropForeign(['suspended_by']);

            $table->dropColumn([
                'is_approved',
                'approved_at',
                'approved_by',
                'suspension_reason',
                'suspended_at',
                'suspended_by',
            ]);
        });
    }
};
