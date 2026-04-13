<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_v2', function (Blueprint $table) {
            $table->string('allowance_of_dogs', 32)->nullable()->after('allow_dogs');
            $table->text('accessibility_description')->nullable()->after('wheelchair_accessible');
            $table->json('description_items')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('venue_v2', function (Blueprint $table) {
            $table->dropColumn([
                'allowance_of_dogs',
                'accessibility_description',
                'description_items',
            ]);
        });
    }
};
