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
            $table->boolean('is_cloudflare')->nullable()->after('remote_domains');
            $table->timestamp('dns_checked_at')->nullable()->after('is_cloudflare');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_items', function (Blueprint $table) {
            $table->dropColumn(['is_cloudflare', 'dns_checked_at']);
        });
    }
};
