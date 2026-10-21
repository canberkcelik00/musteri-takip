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
        Schema::table('server_usages', function (Blueprint $table) {
            $table->json('accounts')->nullable()->after('resources');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_usages', function (Blueprint $table) {
            $table->dropColumn('accounts');
        });
    }
};
