<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('payment_methods')->insert([
            ['name' => 'Havale/EFT', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Nakit', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kredi Kartı', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
