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
        Schema::table('customer_service_items', function (Blueprint $table) {
            $table->boolean('points_to_server')->nullable()->after('registration_checked_at');
            $table->timestamp('points_checked_at')->nullable()->after('points_to_server');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_items', function (Blueprint $table) {
            $table->dropColumn(['points_to_server', 'points_checked_at']);
        });
    }
};
