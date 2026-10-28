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
            $table->date('registration_expires_at')->nullable()->after('ssl_checked_at');
            $table->string('registrar')->nullable()->after('registration_expires_at');
            $table->timestamp('registration_checked_at')->nullable()->after('registrar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_items', function (Blueprint $table) {
            $table->dropColumn(['registration_expires_at', 'registrar', 'registration_checked_at']);
        });
    }
};
