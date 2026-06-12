<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['talents_v2', 'organiser_v2', 'venue_v2'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'show_contact_box')) {
                    $table->boolean('show_contact_box')->default(false)->after('contact_box_design_message');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['talents_v2', 'organiser_v2', 'venue_v2'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'show_contact_box')) {
                    $table->dropColumn('show_contact_box');
                }
            });
        }
    }
};
