<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add new fields to events table.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Event capacity
            $table->integer('capacity')->nullable()->after('is_free');
            
            // Registration details
            $table->string('registration_url', 500)->nullable()->after('capacity');
            $table->timestamp('registration_deadline')->nullable()->after('registration_url');
            
            // SEO and internal fields
            $table->json('meta_keywords')->nullable()->after('meta_description');
            $table->text('internal_notes')->nullable()->after('meta_keywords');
            
            // Indexes for common queries
            $table->index('capacity');
            $table->index('registration_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['capacity']);
            $table->dropIndex(['registration_deadline']);
            $table->dropColumn([
                'capacity',
                'registration_url',
                'registration_deadline',
                'meta_keywords',
                'internal_notes'
            ]);
        });
    }
};
