<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->string('icon', 50)->nullable()->after('description');
            $table->string('color', 7)->nullable()->after('icon');
            $table->integer('display_order')->default(0)->after('color');
            $table->boolean('is_active')->default(true)->after('display_order');
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->softDeletes();
        });

        Schema::table('subcategories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->integer('display_order')->default(0)->after('description');
            $table->boolean('is_active')->default(true)->after('display_order');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'icon', 'color', 'display_order', 'is_active', 'is_featured']);
            $table->dropSoftDeletes();
        });

        Schema::table('subcategories', function (Blueprint $table) {
            $table->dropColumn(['description', 'display_order', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
