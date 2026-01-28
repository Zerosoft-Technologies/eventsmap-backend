<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add contact_email and contact_phone columns to events table.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'contact_email')) {
                $table->string('contact_email', 255)->nullable()->after('organizer_id');
            }
            if (!Schema::hasColumn('events', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable()->after('contact_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_phone']);
        });
    }
};
