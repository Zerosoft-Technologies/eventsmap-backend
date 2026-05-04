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
                if (! Schema::hasColumn('events_v2', 'contact_box_design_message')) {
                    $table->text('contact_box_design_message')->nullable()->after('contact_box_message');
                }
            });
        }

        foreach (['talents_v2', 'organiser_v2', 'venue_v2'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'contact_box_message')) {
                    $table->text('contact_box_message')->nullable()->after('contact_website');
                }
                if (! Schema::hasColumn($tableName, 'contact_box_design_message')) {
                    $table->text('contact_box_design_message')->nullable()->after('contact_box_message');
                }
            });
        }

        if (Schema::hasTable('venues')) {
            Schema::table('venues', function (Blueprint $table) {
                if (! Schema::hasColumn('venues', 'contact_box_message')) {
                    $table->text('contact_box_message')->nullable()->after('website');
                }
                if (! Schema::hasColumn('venues', 'contact_box_design_message')) {
                    $table->text('contact_box_design_message')->nullable()->after('contact_box_message');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events_v2') && Schema::hasColumn('events_v2', 'contact_box_design_message')) {
            Schema::table('events_v2', function (Blueprint $table) {
                $table->dropColumn('contact_box_design_message');
            });
        }

        foreach (['talents_v2', 'organiser_v2', 'venue_v2'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'contact_box_design_message')) {
                    $table->dropColumn('contact_box_design_message');
                }
                if (Schema::hasColumn($tableName, 'contact_box_message')) {
                    $table->dropColumn('contact_box_message');
                }
            });
        }

        if (Schema::hasTable('venues')) {
            Schema::table('venues', function (Blueprint $table) {
                if (Schema::hasColumn('venues', 'contact_box_design_message')) {
                    $table->dropColumn('contact_box_design_message');
                }
                if (Schema::hasColumn('venues', 'contact_box_message')) {
                    $table->dropColumn('contact_box_message');
                }
            });
        }
    }
};
