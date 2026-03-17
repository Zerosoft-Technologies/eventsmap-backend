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
        // Cross-DB cleanup (MySQL + PostgreSQL): keep the lowest id per (category_id, name).
        $duplicates = DB::table('subcategories')
            ->select('category_id', 'name', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as dup_count'))
            ->groupBy('category_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('subcategories')
                ->where('category_id', $dup->category_id)
                ->where('name', $dup->name)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

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

