<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            $table->json('subcategory_ids')->nullable();
            $table->json('invited_talents')->nullable();
            $table->json('invited_organisers')->nullable();
            $table->json('invited_venues')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            $table->dropColumn([
                'subcategory_ids',
                'invited_talents',
                'invited_organisers',
                'invited_venues',
            ]);
        });
    }
};
