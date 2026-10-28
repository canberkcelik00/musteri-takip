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
            $table->unsignedInteger('site_failures')->default(0)->after('points_checked_at');
            $table->timestamp('site_checked_at')->nullable()->after('site_failures');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_items', function (Blueprint $table) {
            $table->dropColumn(['site_failures', 'site_checked_at']);
        });
    }
};
