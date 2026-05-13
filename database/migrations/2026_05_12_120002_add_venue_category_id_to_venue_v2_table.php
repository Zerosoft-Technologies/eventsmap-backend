<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_v2', function (Blueprint $table) {
            if (! Schema::hasColumn('venue_v2', 'venue_category_id')) {
                $table->unsignedBigInteger('venue_category_id')->nullable()->after('subcategory_ids');
                $table->index('venue_category_id');
                $table->foreign('venue_category_id')->references('id')->on('venue_categories')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_v2', function (Blueprint $table) {
            if (Schema::hasColumn('venue_v2', 'venue_category_id')) {
                $table->dropForeign(['venue_category_id']);
                $table->dropIndex(['venue_category_id']);
                $table->dropColumn('venue_category_id');
            }
        });
    }
};
