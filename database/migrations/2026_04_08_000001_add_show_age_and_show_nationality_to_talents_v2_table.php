<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talents_v2', function (Blueprint $table) {
            if (!Schema::hasColumn('talents_v2', 'show_age')) {
                $table->string('show_age', 32)->nullable();
            }
            if (!Schema::hasColumn('talents_v2', 'show_nationality')) {
                $table->string('show_nationality', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('talents_v2', function (Blueprint $table) {
            if (Schema::hasColumn('talents_v2', 'show_age')) {
                $table->dropColumn('show_age');
            }
            if (Schema::hasColumn('talents_v2', 'show_nationality')) {
                $table->dropColumn('show_nationality');
            }
        });
    }
};
