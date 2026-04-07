<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('talents_v2', 'talent_category_id')) {
            Schema::table('talents_v2', function (Blueprint $table) {
                $table->unsignedBigInteger('talent_category_id')->nullable()->after('category_id');
                $table->index('talent_category_id');
            });
        }

        Schema::table('talents_v2', function (Blueprint $table) {
            $table->foreign('talent_category_id')->references('id')->on('talent_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('talents_v2', function (Blueprint $table) {
            $table->dropForeign(['talent_category_id']);
            $table->dropIndex(['talent_category_id']);
            $table->dropColumn('talent_category_id');
        });
    }
};
