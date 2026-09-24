<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The new unique index is added before the old one is dropped, so the
     * `payment_id` foreign key always keeps an index starting with it.
     */
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->string('source')->default('payment')->after('amount');

            $table->unique(['payment_id', 'charge_id', 'source']);
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropUnique(['payment_id', 'charge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->unique(['payment_id', 'charge_id']);
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropUnique(['payment_id', 'charge_id', 'source']);
            $table->dropColumn('source');
        });
    }
};
