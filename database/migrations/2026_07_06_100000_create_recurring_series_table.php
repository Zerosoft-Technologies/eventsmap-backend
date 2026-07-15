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
        Schema::create('recurring_series', function (Blueprint $table) {
            $table->id();

            /** Event owner / organiser (same semantics as events_v2.user_id). */
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();

            /**
             * Discriminator for recurrence_rules shape.
             * @see \App\Support\Recurrence\RecurrenceType
             */
            $table->string('recurrence_type', 32);

            /**
             * Type-specific rule payload (JSON). Extensible for bi-weekly, monthly, yearly,
             * and future multiple time_slots without schema changes.
             */
            $table->json('recurrence_rules');

            /** IANA timezone identifier only (e.g. Europe/Amsterdam). Never UTC offsets. */
            $table->string('timezone', 64);

            /** First calendar date of the series (in series timezone). */
            $table->date('start_date');

            /** Optional last calendar date; null = open-ended (materialization horizon enforced in app layer). */
            $table->date('end_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('organizer_id');
            $table->index('recurrence_type');
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['organizer_id', 'recurrence_type']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_series');
    }
};
