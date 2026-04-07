<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('organiser_v2', 'organiser_category_id')) {
            Schema::table('organiser_v2', function (Blueprint $table) {
                $table->unsignedBigInteger('organiser_category_id')->nullable()->after('category_id');
                $table->index('organiser_category_id');
            });
        }

        Schema::table('organiser_v2', function (Blueprint $table) {
            $table->foreign('organiser_category_id')->references('id')->on('organiser_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organiser_v2', function (Blueprint $table) {
            $table->dropForeign(['organiser_category_id']);
            $table->dropIndex(['organiser_category_id']);
            $table->dropColumn('organiser_category_id');
        });
    }
};
