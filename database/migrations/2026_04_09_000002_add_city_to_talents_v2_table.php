<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talents_v2', function (Blueprint $table) {
            if (!Schema::hasColumn('talents_v2', 'city')) {
                $table->string('city', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('talents_v2', function (Blueprint $table) {
            if (Schema::hasColumn('talents_v2', 'city')) {
                $table->dropColumn('city');
            }
        });
    }
};
