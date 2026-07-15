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
        Schema::table('recurring_series', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false)->after('end_date');
            $table->timestamp('approved_at')->nullable()->after('is_approved');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();

            $table->index('is_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_series', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['is_approved']);
            $table->dropColumn(['is_approved', 'approved_at', 'approved_by']);
        });
    }
};
