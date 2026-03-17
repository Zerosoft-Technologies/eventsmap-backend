<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If duplicates already exist (e.g. from previous seeding), remove them before adding the constraint.
        // Keep the lowest id for each (category_id, name) group.
        DB::statement(<<<'SQL'
            DELETE FROM subcategories s
            USING subcategories d
            WHERE s.category_id = d.category_id
              AND s.name = d.name
              AND s.id > d.id
        SQL);

        Schema::table('subcategories', function (Blueprint $table) {
            $table->unique(['category_id', 'name'], 'subcategories_category_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subcategories', function (Blueprint $table) {
            $table->dropUnique('subcategories_category_id_name_unique');
        });
    }
};

