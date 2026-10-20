<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a hosting catalog service (WHM package) and a customer service item (WHM account or
 * cPanel domain) to the server they were pulled from. Rows without a server stay as they are.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catalog_services', function (Blueprint $table) {
            $table->foreignId('server_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('remote_package')->nullable()->after('server_id');
            $table->unique(['server_id', 'remote_package']);
        });

        Schema::table('customer_service_items', function (Blueprint $table) {
            $table->foreignId('server_id')->nullable()->after('catalog_service_id')->constrained()->restrictOnDelete();
            $table->string('remote_key')->nullable()->after('server_id');
            $table->unique(['server_id', 'remote_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_items', function (Blueprint $table) {
            $table->dropUnique(['server_id', 'remote_key']);
            $table->dropConstrainedForeignId('server_id');
            $table->dropColumn('remote_key');
        });

        Schema::table('catalog_services', function (Blueprint $table) {
            $table->dropUnique(['server_id', 'remote_package']);
            $table->dropConstrainedForeignId('server_id');
            $table->dropColumn('remote_package');
        });
    }
};
