<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events_v2')) {
            return;
        }

        Schema::table('events_v2', function (Blueprint $table) {
            if (! Schema::hasColumn('events_v2', 'show_contact_box')) {
                $table->boolean('show_contact_box')->default(false)->after('contact_box_design_message');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events_v2')) {
            return;
        }

        Schema::table('events_v2', function (Blueprint $table) {
            if (Schema::hasColumn('events_v2', 'show_contact_box')) {
                $table->dropColumn('show_contact_box');
            }
        });
    }
};
