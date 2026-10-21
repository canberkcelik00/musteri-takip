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
        Schema::table('account_usages', function (Blueprint $table) {
            $table->json('warned_levels')->nullable()->after('failure_reason');
        });

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->string('warned_level')->nullable()->after('incoming_suspended');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn('warned_level');
        });

        Schema::table('account_usages', function (Blueprint $table) {
            $table->dropColumn('warned_levels');
        });
    }
};
