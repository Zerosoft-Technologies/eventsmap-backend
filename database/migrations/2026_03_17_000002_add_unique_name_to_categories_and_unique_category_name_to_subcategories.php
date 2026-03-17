<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categories: ensure name is unique (requirement).
        // Cross-DB cleanup (MySQL + PostgreSQL): keep the lowest id per name.
        $duplicates = DB::table('categories')
            ->select('name', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as dup_count'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('categories')
                ->where('name', $dup->name)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('name', 'categories_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_unique');
        });
    }
};

