<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            $table->foreignId('series_id')
                ->nullable()
                ->after('user_id')
                ->constrained('recurring_series')
                ->nullOnDelete();

            /**
             * True when this instance was edited individually and no longer matches
             * the series template (exception instance).
             */
            $table->boolean('is_modified')->default(false)->after('series_id');

            $table->index('series_id');
            $table->index(['series_id', 'event_date']);
            $table->index(['series_id', 'is_modified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            $table->dropForeign(['series_id']);
            $table->dropIndex(['series_id']);
            $table->dropIndex(['series_id', 'event_date']);
            $table->dropIndex(['series_id', 'is_modified']);
            $table->dropColumn(['series_id', 'is_modified']);
        });
    }
};
